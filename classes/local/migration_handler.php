<?php

namespace tool_opensesame\local;

use tool_opensesame\local\data\base;

abstract class migration_handler {

    const TRANSFORM_DATE = 'date';

    const TRANSFORM_COMMA_IMPLODE = 'commaimplode';

    const TRANSFORM_EXTRACT_FIRST = 'extractfirst';

    /**
     * Gets the classname of a given object that represents an entity.
     * @param mixed $entity
     * @return string
     */
    private function get_entity_name($entity): string {
        $classparts = explode('\\', get_class($entity));
        return end($classparts);
    }

    /**
     * Process an array of entities.
     * @param array|\Generator $entities
     * @param mixed $api
     * @param array $endstatus
     */
    public function process_and_log_entities($entities, \curl $api, $endstatus = []) {
        foreach ($entities as $entity) {
            $this->process_and_log_entity($entity, $api, $endstatus);
        }
    }

    /**
     * Processes an entity until it reaches it's end status.
     * @param base $entity
     * @param mixed $api
     * @param array $endstatus
     */
    private function process_and_log_entity(base &$entity, $api, $endstatus = []): void {
        $entityname = $this->get_entity_name($entity);
        if (empty($endstatus)) {
            $laststep = $entity->get_last_step();
            if ($laststep === false) {
                return;
            }
            $endstatus = [$laststep => true];
        }
        try {
            $message = '';
            while (
                    !isset($endstatus[$entity->status])
                    && empty($message)) {
                $message = $this->process_step($entity, $api);
            }

            if (!empty($message)) {
                mtrace("[ERROR][$entityname] Processing of $entityname with ID {$entity->id} halted/skipped: " . $message);
            }
        } catch (\Exception $ex) {
            mtrace("[ERROR][$entityname] Error processing $entityname with ID {$entity->id}");
            mtrace($ex->getMessage());
            mtrace($ex->getTraceAsString());
        }
    }

    /**
     * Process mapping and assign values to the entity.
     *
     * @param mixed $todata
     * @param mixed $fromdata
     * @param array $mappings
     * @param array $transforms
     * @return string
     * @throws \coding_exception
     */
    protected function process_mappings(&$todata, $fromdata, array $mappings, array $transforms = []) {
        foreach ($mappings as $fromcolumn => $tocolumn) {
            $attributelevels = explode(',', $fromcolumn);
            $parententity = $fromdata;
            foreach ($attributelevels as $attribute) {
                if (is_object($parententity)) {
                    try {
                        $parententity = $parententity->{$attribute};
                    } catch (\Exception $ex) {
                        $vars = array_keys(get_object_vars($parententity));
                        $varsstr = implode(', ', $vars);
                        throw new \coding_exception("Could not find $attribute in $varsstr");
                    }
                }
            }
            $value = $parententity;
            if (isset($transforms[$fromcolumn])) {
                $transform = $transforms[$fromcolumn];
                $value = $this->process_data_transform($value, $transform);
            }
            $todata->{$tocolumn} = $value;
        }
        return '';
    }

    /**
     * Processes a data transformation for a field.
     *
     * @param mixed $value
     * @param string $transform
     * @return false|int|string
     */
    protected function process_data_transform($value, string $transform) {
        $res = $value;
        switch ($transform) {
            case self::TRANSFORM_DATE:
                $res = strtotime($value);
                break;
            case self::TRANSFORM_COMMA_IMPLODE:
                $res = implode(', ', $value);
                break;
            case self::TRANSFORM_EXTRACT_FIRST:
                $res = reset($value);
                break;
            default:
                break;
        }
        return $res;
    }

    /**
     * Processes a single step for an entity.
     * @param base $entity
     * @param \curl $api
     * @return string Message with errors or empty if none.
     */
    protected function process_step(base &$entity, $api): string {
        $step = $entity->status;
        $nextstep = $entity->get_next_step($step);
        $message = "Could not find next step for $step";
        if ($nextstep !== false) {
            $method = "process_{$step}_to_{$nextstep}";
            $entityname = $this->get_entity_name($entity);
            mtrace("[INFO][$entityname][$method] $entityname ID: {$entity->id}");
            $message = $this->{$method}($entity, $api);
            if (empty($message)) {
                $entity->status = $nextstep;
            }
            $entity->mtrace_errors_save();
        }
        return $message;
    }
}
