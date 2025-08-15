<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('organiser.intro', __('admin/pages/manage-organiser-panel.form.intro.default'));
    }
};
