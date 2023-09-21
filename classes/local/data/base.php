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

/**
 * A base persistence class.
 *
 * @copyright  2023 Moodle
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @author     David Castro <david.castro@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    tool_opensesame
 */

namespace tool_opensesame\local\data;

use core\persistent;

/**
 * A base persistence class.
 *
 * @copyright  2023 Moodle
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @author     David Castro <david.castro@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    tool_opensesame
 * @property int $id
 * @property int $usermodified
 * @property int $timecreated
 * @property int $timemodified
 * @property array $steps
 */
abstract class base extends persistent {

    /** @var static[][] Array of static instances. */
    protected static $instances = [];

    /** @var string[] Array of steps. */
    protected static $steps = [];

    /**
     * Get a statically cached instance of this object.
     *
     * @param int $id
     * @param bool $reload
     * @return static|null
     */
    public static function get_instance(int $id, bool $reload = false): ?base {
        if (!isset(static::$instances[static::class])) {
            static::$instances[static::class] = [];
        }

        if (!array_key_exists($id, static::$instances[static::class]) || $reload) {
            static::$instances[static::class][$id] = static::get_record(['id' => $id]) ?: null;
        }

        return static::$instances[static::class][$id];
    }

    /**
     * Reset the instances cache for the called object class.
     *
     * @param bool $alltypes If ture, reset instance caches for all types related the base class.
     * @return void
     */
    public static function reset_instance_cache(bool $alltypes = false): void {
        if ($alltypes) {
            static::$instances = [];
        } else {
            static::$instances[static::class] = [];
        }
    }

    /**
     * Magic set method.
     *
     * @param string $name
     * @param mixed $value
     * @return base
     * @throws \coding_exception
     */
    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    /**
     * Magic get method.
     *
     * @param string $name name to return value of.
     * @return bool|mixed|null
     * @throws \coding_exception
     */
    public function __get($name) {
        if (is_callable([$this, "get_$name"])) {
            return $this->{"get_$name"}();
        }
        return $this->get($name);
    }

    /**
     * Magic isset method.
     *
     * @param string $name
     * @return bool
     * @throws \coding_exception
     */
    public function __isset($name) {
        if (!static::has_property($name)) {
            return false;
        }

        if ($this->get($name) === null) {
            return false;
        }

        return true;
    }

    /**
     * Magic unset method.
     *
     * @param string $name
     * @return void
     * @throws \coding_exception
     */
    public function __unset($name) {
        // Return null because we don't support unsetting.
        $this->raw_set($name, null);
    }

    /**
     * Load a list of records into a recordset.
     *
     * @param array $filters Filters to apply.
     * @param string $sort Field to sort by.
     * @param string $order Sort order.
     * @param int $skip Limitstart.
     * @param int $limit Number of rows to return.
     *
     * @return \Generator|static[]
     */
    public static function get_recordset($filters = [], $sort = '', $order = 'ASC', $skip = 0, $limit = 0) {
        global $DB;

        $orderby = '';
        if (!empty($sort)) {
            $orderby = $sort . ' ' . $order;
        }

        $records = $DB->get_recordset(static::TABLE, $filters, $orderby, '*', $skip, $limit);
        foreach ($records as $record) {
            $newrecord = new static(0, $record);
            yield $newrecord;
        }
        $records->close();
    }

    /**
     * Adds validation errors to the trace.
     */
    public function mtrace_validation_errors() {
        if (defined('CLI_SCRIPT')) {
            if (!empty($errors = $this->get_errors())) {
                $classparts = explode('\\', get_class($this));
                $classname  = end($classparts);
                mtrace("[ERROR][$classname] Error saving the entity ");
                foreach ($errors as $field => $message) {
                    mtrace("[ERROR][$classname][$field] $message");
                    mtrace("[ERROR][$classname][$field] Value: " . $this->{$field});
                }
            }
        }
    }

    /**
     * Outputs errors and saves.
     */
    public function mtrace_errors_save() {
        $this->mtrace_validation_errors();
        $this->save();
    }

    /**
     * Retrieves the list of steps.
     * @return array
     */
    public static function get_steps(): array {
        return static::$steps;
    }

    /**
     * Gets the step identifier that follows a given step.
     * @param string $step
     * @return string|bool
     */
    public function get_next_step(string $step) {
        $stepindex = array_search($step, static::$steps);
        $size = count(static::$steps);
        if ($stepindex !== false && $stepindex < ($size - 1)) {
            return static::$steps[$stepindex + 1];
        }
        return false;
    }

    /**
     * Gets the first step identifier.
     * @return string|bool
     */
    public function get_first_step(): string {
        if (!empty(static::$steps)) {
            return reset(static::$steps);
        }
        return false;
    }

    /**
     * Gets the last step identifier.
     * @return string|bool
     */
    public function get_last_step(): string | bool {
        if (!empty(static::$steps)) {
            return end(static::$steps);
        }
        return false;
    }
}
