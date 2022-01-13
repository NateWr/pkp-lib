<?php
/**
 * @file classes/decision/DAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Read and write decisions to the database.
 */

namespace PKP\decision;

use APP\decision\Decision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use stdClass;

class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = \PKP\services\PKPSchemaService::SCHEMA_DECISION;

    /** @copydoc EntityDAO::$table */
    public $table = 'edit_decisions';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = '';

    /** @copydoc EntityDAO::$primarykeyColumn */
    public $primaryKeyColumn = 'edit_decision_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'edit_decision_id',
        'dateDecided' => 'date_decided',
        'decision' => 'decision',
        'editorId' => 'editor_id',
        'reviewRoundId' => 'review_round_id',
        'round' => 'round',
        'stageId' => 'stage_id',
        'submissionId' => 'submission_id',
    ];

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Decision
    {
        return App::make(Decision::class);
    }

    /**
     * @copydoc EntityDAO::get()
     */
    public function get(int $id): ?Decision
    {
        return parent::get($id);
    }

    /**
     * Get the number of decisions matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('ed.' . $this->primaryKeyColumn)
            ->pluck('ed.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of decisions matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->select(['ed.*'])
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(stdClass $row): Decision
    {
        return parent::fromRow($row);
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Decision $decision): int
    {
        return parent::_insert($decision);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(Decision $decision)
    {
        parent::_update($decision);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Decision $decision)
    {
        parent::_delete($decision);
    }

    /**
     * Reassign all decisions from one editor to another
     */
    public function reassignDecisions(int $fromEditorId, int $toEditorId)
    {
        DB::table($this->table)
            ->where('editor_id', '=', $fromEditorId)
            ->update(['editor_id' => $toEditorId]);
    }
}
