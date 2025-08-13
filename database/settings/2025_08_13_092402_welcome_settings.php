<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('welcome.title', config('app.name'));
        $this->migrator->add('welcome.tagline', __('admin/pages/manage-welcome.form.tagline.default'));
        $this->migrator->add('welcome.preview_image', null);
        $this->migrator->add('welcome.intro', __('admin/pages/manage-welcome.form.intro.default'));
        $this->migrator->add('welcome.usps', null);
        $this->migrator->add('welcome.outro', null);
        $this->migrator->add('welcome.faqs', null);
    }
};
