<?php
/**
 * @file classes/announcement/Collector.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class announcement
 *
 * @brief A helper class to build a query for querying a collection of announcements
 */

namespace PKP\Context;

use App\Context\DAO;
use HookRegistry;
use Illuminate\Database\Capsule\Manager as DB;
use PKP\Core\Interfaces\EntityCollectorInterface;

class Collector implements EntityCollectorInterface
{
    public string $searchPhrase = '';
    public int $userId;
    public bool $isEnabled;

    /**
     * Filter by enabled status
     */
    public function filterByIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }

    /**
     * Filter by whether or not the user can access
     *
     * The user id can access contexts where they are assigned to
     * a user group. If the context is disabled, they must be
     * assigned to ROLE_ID_MANAGER user group.
     */
    public function filterByUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Filter by those matching a search query
     */
    public function searchPhrase(string $phrase): self
    {
        $this->searchPhrase = $phrase;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(int $count): self
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @copydoc EntityCollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): \Illuminate\Database\Query\Builder
    {
        $this->columns[] = 'c.*';
        $q = DB::table(DAO::TABLE . ' as c')
            ->leftJoin(DAO::SETTINGS_TABLE . ' as cs', 'cs.' . DAO::PRIMARY_KEY_COLUMN, '=', 'c.' . DAO::PRIMARY_KEY_COLUMN)
            ->groupBy('c.' . DAO::PRIMARY_KEY_COLUMN);

        if (isset($this->isEnabled)) {
            if (!empty($this->isEnabled)) {
                $q->where('c.enabled', '=', 1);
            } elseif ($this->isEnabled === false) {
                $q->where('c.enabled', '!=', 1);
            }
        }

        if (!empty($this->userId)) {
            $q->leftJoin('user_groups as ug', 'ug.context_id', '=', 'c.' . DAO::PRIMARY_KEY_COLUMN)
                ->leftJoin('user_user_groups as uug', 'uug.user_group_id', '=', 'ug.user_group_id')
                ->where(function ($q) {
                    $q->where('uug.user_id', '=', $this->userId)
                        ->where(function ($q) {
                            $q->where('ug.role_id', '=', ROLE_ID_MANAGER)
                                ->orWhere('c.enabled', '=', 1);
                        });
                });
        }

        // search phrase
        if (!empty($this->searchPhrase)) {
            $words = explode(' ', $this->searchPhrase);
            if (count($words)) {
                foreach ($words as $word) {
                    $q->where(function ($q) use ($word) {
                        $q->where(function ($q) use ($word) {
                            $q->where('cs.setting_name', 'name');
                            $q->where('cs.setting_value', 'LIKE', "%{$word}%");
                        })
                            ->orWhere(function ($q) use ($word) {
                                $q->where('cs.setting_name', 'description');
                                $q->where('cs.setting_value', 'LIKE', "%{$word}%");
                            })
                            ->orWhere(function ($q) use ($word) {
                                $q->where('cs.setting_name', 'acronym');
                                $q->where('cs.setting_value', 'LIKE', "%{$word}%");
                            })
                            ->orWhere(function ($q) use ($word) {
                                $q->where('cs.setting_name', 'abbreviation');
                                $q->where('cs.setting_value', 'LIKE', "%{$word}%");
                            });
                    });
                }
            }
        }

        // Add app-specific query statements
        HookRegistry::call('Context::getContexts::queryObject', [&$q, $this]);

        $q->select($this->columns);

        return $q;
    }
}
