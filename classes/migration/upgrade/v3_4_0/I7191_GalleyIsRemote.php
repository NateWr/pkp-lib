<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7191_GalleyIsRemote.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7191_GalleyIsRemote
 * @brief Add the is_remote column to the galley table
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class I7191_GalleyIsRemote extends \PKP\migration\Migration
{
    public function up(): void
    {
        Schema::table('publication_galleys', function (Blueprint $table) {
            $table->boolean('is_remote')->default(false);
        });

        DB::table('publication_galleys')
            ->where('remote_url', '')
            ->whereNull('submission_file_id')
            ->update(['is_remote' => true]);
    }

    public function down(): void
    {
        Schema::table('publication_galleys', function (Blueprint $table) {
            $table->dropColumn('is_remote');
        });
    }
}
