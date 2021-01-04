<?php

/**
 * @file tools/buildHookDocs.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class buildHookDocs
 * @ingroup tools
 *
 * @brief CLI tool to compile documentation on hooks in markdown
 */
define('APP_ROOT', dirname(dirname(dirname(dirname(__FILE__)))));
require(APP_ROOT . '/tools/bootstrap.inc.php');

class buildHookDocs extends CommandLineTool {

	/** @var string Where to write the generated markdown file */
	public string $outputFile = '';

	/** @var array Hooks */
	public array $hooks = [];

	/** @var array Directories to exclude from indexing. (.gitignore dirs will be added to this list) */
	public array $excludePaths = [
		'./.git',
		'./lib/pkp/.git',
		'./lib/pkp/lib/vendor',
		'./lib/ui-library',
		'./lib/pkp/tools/buildHookDocs.php'
	];

	/**
	 * Constructor.
	 * @param $argv array command-line arguments (see usage)
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);
		$this->outputFile = array_shift($this->argv) ?? '';
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Command-line tool to compile markdown file with hook documentation\n"
			. "Usage:\n"
			. "\tphp {$this->scriptName} [outputFile]: Compile markdown file and save to [outputFile]\n"
			. "\tphp {$this->scriptName} usage: Display usage information this tool\n";
	}

	/**
	 * Parse and execute the import/export task.
	 */
	function execute() {
		if (empty($this->outputFile)) {
			$this->usage();
			exit();
		} elseif ((file_exists($this->outputFile) && !is_writable($this->outputFile)) ||
				(!is_writeable(dirname($this->outputFile)))) {
			echo "You do not have permission to write to $this->outputFile.\n";
			exit;
		} else {
			$this->loadIgnoreDirs(APP_ROOT . '/.gitignore');
			$this->loadIgnoreDirs(APP_ROOT . '/lib/pkp/.gitignore', './lib/pkp/');

			$this->processDir('./', function($fileName) {
				$file = file_get_contents($fileName);

				// Get all hooks in the file
				$lines = explode("\n", $file);
				foreach ($lines as $i => $line) {
					if (strpos($line, 'HookRegistry::call') === false) {
						continue;
					}
					preg_match('/HookRegistry\:\:call\(\'([\d\D]*?)\'[^\&\$]*([^\)\]]*)/', $line, $matches);
					if (empty($matches) || empty($matches[1])) {
						echo "Could not get documentation for hook on line " . ($i + 1) . " of $fileName.\n";
						continue;
					}
					$hook = new stdClass();
					$hook->name = $matches[1];
					$hook->params = empty($matches[2])
						? []
						: array_map('trim', explode(',', $matches[2]));
					$hook->group = explode('::', $hook->name)[0];
					$hook->file = $fileName;
					$hook->line = $i + 1;
					$this->hooks[] = $hook;
				}

				// Augment hook info with docblocks
				// The four backslashes, \\\\, are necessary to use the characters \\? in the regex.
				if (preg_match_all('/\/\*(?:[^*]|\n|(?:\*(?:[^\/]|\n)))*\*\/[\s]*\\\\?HookRegistry::call\((?:\'([\d\D]*?)\')?/', $file, $matches, PREG_SET_ORDER)){
					foreach ($matches as $match) {
						$hookHasVariable = false;

						if (!empty($match[1])) {
							$hook = $this->getHook($match[1]);

						// If there's no match on the hook, this is probably a hook with a variable in the
						// name. Use the @hook tag instead
						} else {
							preg_match('/@hook[\s]*([^\n]*)/', $match[0], $hookMatches);
							if (!isset($hookMatches[1])) {
								echo "Could not get hook for " . $hookMatches[0];
								continue;
							}
							$hook = new stdClass();
							$hook->name = $hookMatches[1];
							$hook->params = [];
							$this->hooks[] = $hook;
							$hookHasVariable = true;
						}

						// Get summary/description
						$lines = explode("\n", $match[0]);
						$lines  = array_slice($lines, 1, -2);
						$cleanLines = [];
						foreach ($lines as $line) {
							if (strpos($line, '* @') !== false) {
								continue;
							}
							$cleanLines[] = preg_replace('/[\t]*\s\*\s?/', '', $line);
						}
						$hook->summary = array_shift($cleanLines);
						$hook->description = trim(join("\n", $cleanLines));

						// Get tags
						preg_match_all('/\@[^\n]*/', $match[0], $tagMatches);
						if (isset($tagMatches[0])) {
							foreach ($tagMatches[0] as $tag) {
								$parts = explode(' ', $tag);
								$tagName = array_shift($parts);
								switch ($tagName) {
									case '@hook':
										$hook->hook = join(' ', $parts);
										break;
									case '@group':
										$hook->group = join(' ', $parts);
										break;
									case '@param':
										$type = array_shift($parts);
										$name = array_shift($parts);
										$description = join(' ', $parts);
										if (!$type || !$name || substr($name, 0, 1) !== '$') {
											throw new Exception('Error with variable type or name in @param doc ' . $tag . ' in ' . $file);
										}
										// Can't autodetect params in hooks with variables
										// so use the docblock without checking the code
										if ($hookHasVariable) {
											$hook->params[] = [
												'type' => $type,
												'name' => $name,
												'description' => $description,
											];
										} else {
											$hasParam = false;
											foreach ($hook->params as $i => $param) {
												$unreferencedName = str_replace('&', '', $param);
												if ($unreferencedName !== $name) {
													continue;
												}
												$hasParam = true;
												$hook->params[$i] = [
													'type' => $type,
													'name' => $name,
													'description' => $description,
												];
											}
											if (!$hasParam) {
												throw new Exception('A defined @param, ' . $name . ', could not be found in the hook ' . $hook);
											}
										}
										break;
								}
							}
						}
					}
				}
			});

			$this->createMarkdown($this->hooks);
		}
	}

	/**
	 * Recursive function to find hook docblocks in a directory
	 */
	public function processDir(string $dir, callable $function) {
		foreach (new DirectoryIterator($dir) as $fileInfo) {
			$isExcluded = false;
			foreach ($this->excludePaths as $excludePath) {
				if (strpos($fileInfo->getPathname(), $excludePath) === 0) {
					$isExcluded = true;
					break;
				}
			}
			if ($isExcluded) {
				continue;
			}
			if (!$fileInfo->isDot()) {
				if ($fileInfo->isDir()) {
					$this->processDir($fileInfo->getPathname(), $function);
				} else {
					call_user_func($function, $dir . '/' . $fileInfo->getFilename());
				}
			}
		}
	}

	/**
	 * Load a .gitignore file and add to the excluded to directories
	 *
	 * @param string $path Path and filename for gitignore file
	 * @param string $prefix A prefix to give to each of the paths in the gitignore file
	 */
	public function loadIgnoreDirs(string $path, $prefix = '') {
		$gitIgnore = file_get_contents($path);
		$gitIgnorePaths = explode("\n", $gitIgnore);
		foreach ($gitIgnorePaths as $gitIgnorePath) {
			if (substr($gitIgnorePath, 0, 1) === '#') {
				continue;
			} elseif (substr($gitIgnorePath, 0,1) === '/') {
				$gitIgnorePath = '.' . $gitIgnorePath;
			} elseif (strpos($gitIgnorePath, '.') === 0) {
				if (strpos($gitIgnorePath, '/') !== 1) {
					$gitIgnorePath = '';
				}
			} elseif (substr($gitIgnorePath, 0, 2) !== './') {
				$gitIgnorePath = './' . $gitIgnorePath;
			}
			if ($gitIgnorePath) {
				$this->excludePaths[] = $prefix . $gitIgnorePath;
			}
		}
	}

	/**
	 * Create a markdown file with the hooks
	 *
	 * @param array $hooks
	 */
	public function createMarkdown(array $hooks) {
		usort($hooks, function($a, $b) {
			if ($a->group === $b->group) {
				return strnatcmp($a->name, $b->name);
			}
			return $a->group > $b->group;
		});
		$currentGroup = '';
		foreach ($hooks as $hook) {
			if ($hook->group !== $currentGroup) {
				$currentGroup = $hook->group;
				echo "## $currentGroup\n";
			}
			echo "### $hook->name\n";
			if (!empty($hook->summary)) {
				echo "$hook->summary\n\n";
			}
			if (!empty($hook->description)) {
				echo "$hook->description\n\n";
			}
			foreach ($hook->params as $param) {
				if (is_string($param)) {
					echo $param . "\n\n";
				} else {
					echo '`' . $param['type'] . '` `' . $param['name'] . '` ' . $param['description'] . "\n\n";
				}
			}
			echo "
```
HookRegistry::register('$hook->name', function(\$hookName, \$params) {
";
			foreach ($hook->params as $i => $param) {
				if (is_string($param)) {
					if (strpos($param, '&') === 0) {
						echo "\t" . substr($param, 1) . ' = &$args[' . $i . '];';
					} else {
						echo "\t$param = \$args[$i]";
					}
				} else {
					if (strpos($param, '&') === 0) {
						echo "\t" . $param['name'] . " = &\$args[$i]";
					} else {
						echo "\t" . $param['name'] . " = \$args[$i]";
					}
				}
				echo "\n";
			}

			echo "
	...
});
```\n\n";
		}
	}

	/**
	 * Get a hook by name
	 *
	 * @param string name
	 * @return Object
	 */
	public function getHook($name) {
		foreach ($this->hooks as $hook) {
			if ($hook->name === $name) {
				return $hook;
			}
		}
	}
}

$tool = new buildHookDocs(isset($argv) ? $argv : array());
$tool->execute();