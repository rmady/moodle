<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

use core_reportbuilder\local\helpers\report as helper;
use core_reportbuilder\local\models\column;
use core_reportbuilder\local\models\filter;
use core_reportbuilder\local\models\report;

/**
 * Report builder test generator
 *
 * @package     core_reportbuilder
 * @copyright   2021 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_reportbuilder_generator extends component_generator_base {

    /**
     * Create report
     *
     * @param array|stdClass $record
     * @return report
     * @throws coding_exception
     */
    public function create_report($record): report {
        $record = (array) $record;

        if (!array_key_exists('name', $record)) {
            throw new coding_exception('Record must contain \'name\' property');
        }
        if (!array_key_exists('source', $record)) {
            throw new coding_exception('Record must contain \'source\' property');
        }

        // Include default setup unless specifically disabled in passed record.
        $default = (bool) ($record['default'] ?? true);

        return helper::create_report((object) $record, $default);
    }

    /**
     * Create report column
     *
     * @param array|stdClass $record
     * @return column
     * @throws coding_exception
     */
    public function create_column($record): column {
        $record = (array) $record;

        if (!array_key_exists('reportid', $record)) {
            throw new coding_exception('Record must contain \'reportid\' property');
        }
        if (!array_key_exists('uniqueidentifier', $record)) {
            throw new coding_exception('Record must contain \'uniqueidentifier\' property');
        }

        return helper::add_report_column($record['reportid'], $record['uniqueidentifier']);
    }

    /**
     * Create report filter
     *
     * @param array|stdClass $record
     * @return filter
     * @throws coding_exception
     */
    public function create_filter($record): filter {
        $record = (array) $record;

        if (!array_key_exists('reportid', $record)) {
            throw new coding_exception('Record must contain \'reportid\' property');
        }
        if (!array_key_exists('uniqueidentifier', $record)) {
            throw new coding_exception('Record must contain \'uniqueidentifier\' property');
        }

        return helper::add_report_filter($record['reportid'], $record['uniqueidentifier']);
    }

    /**
     * Create report condition
     *
     * @param array|stdClass $record
     * @return filter
     * @throws coding_exception
     */
    public function create_condition($record): filter {
        $record = (array) $record;

        if (!array_key_exists('reportid', $record)) {
            throw new coding_exception('Record must contain \'reportid\' property');
        }
        if (!array_key_exists('uniqueidentifier', $record)) {
            throw new coding_exception('Record must contain \'uniqueidentifier\' property');
        }

        return helper::add_report_condition($record['reportid'], $record['uniqueidentifier']);
    }
}