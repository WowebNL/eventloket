<?php

declare(strict_types=1);

namespace App\EventForm\Schema;

use Filament\Forms\Components\FileUpload;
use ReflectionObject;

/**
 * The state keys of the file upload fields in the event form.
 *
 * Found by walking {@see EventFormSchema} instead of hard-coding them, so a
 * file upload that is added to the form is picked up automatically.
 * Reflection is needed because there is no Livewire container in a queue job.
 */
final class FileUploadFields
{
    /** @var list<string>|null */
    private static ?array $keys = null;

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return self::$keys ??= self::collect();
    }

    /**
     * @return list<string>
     */
    private static function collect(): array
    {
        $keys = [];
        $walk = function (object $component) use (&$walk, &$keys): void {
            if ($component instanceof FileUpload) {
                $name = $component->getName();
                if ($name !== '') {
                    $keys[] = $name;
                }
            }
            if (! property_exists($component, 'childComponents')) {
                return;
            }
            $reflection = new ReflectionObject($component);
            $prop = $reflection->getProperty('childComponents');
            $prop->setAccessible(true);
            $children = $prop->getValue($component);
            if (! is_array($children)) {
                return;
            }
            foreach ($children as $list) {
                if (! is_array($list)) {
                    continue;
                }
                foreach ($list as $child) {
                    if (is_object($child)) {
                        $walk($child);
                    }
                }
            }
        };

        foreach (EventFormSchema::steps() as $step) {
            $walk($step);
        }

        return array_values(array_unique($keys));
    }
}
