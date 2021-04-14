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

namespace PKP\Announcement;

use HookRegistry;
use Illuminate\Database\Capsule\Manager as DB;
use PKP\Core\Interfaces\EntityCollectorInterface;

class Collector implements EntityCollectorInterface
{
    public array $contextIds = [];
    public string $searchPhrase = '';
    public array $typeIds = [];
    public int $count;
    public int $offset;

    /**
     * Filter announcements by one or more contexts
     */
    public function filterByContextIds(array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter announcements by one or more announcement types
     */
    public function filterByTypeIds(array $typeIds): self
    {
        $this->typeIds = $typeIds;
        return $this;
    }

    /**
     * Filter announcements by those matching a search query
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
        $qb = DB::table(DAO::TABLE . ' as a');

        if (!empty($this->contextIds)) {
            $qb->whereIn('a.assoc_id', $this->contextIds);
        }

        if (!empty($this->typeIds)) {
            $qb->whereIn('a.type_id', $this->typeIds);
        }

        if (!empty($this->searchPhrase)) {
            $words = explode(' ', $this->searchPhrase);
            if (count($words)) {
                $qb->leftJoin(DAO::SETTINGS_TABLE . ' as as', 'a.announcement_id', '=', 'as.announcement_id');
                foreach ($words as $word) {
                    $word = strtolower(addcslashes($word, '%_'));
                    $qb->where(function ($qb) use ($word) {
                        $qb->where(function ($qb) use ($word) {
                            $qb->where('as.setting_name', 'title');
                            $qb->where(DB::raw('lower(as.setting_value)'), 'LIKE', "%{$word}%");
                        })
                            ->orWhere(function ($qb) use ($word) {
                                $qb->where('as.setting_name', 'descriptionShort');
                                $qb->where(DB::raw('lower(as.setting_value)'), 'LIKE', "%{$word}%");
                            })
                            ->orWhere(function ($qb) use ($word) {
                                $qb->where('as.setting_name', 'description');
                                $qb->where(DB::raw('lower(as.setting_value)'), 'LIKE', "%{$word}%");
                            });
                    });
                }
            }
        }

        $qb->orderBy('a.date_posted', 'desc');
        $qb->groupBy('a.announcement_id');

        if ($this->count) {
            $qb->limit($this->count);
        }

        if ($this->offset) {
            $qb->offset($this->count);
        }

        HookRegistry::call('Announcement::Collector', [&$qb, $this]);

        return $qb;
    }
}
