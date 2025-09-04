<?php

namespace App\Filament\Admin\Pages;

use Filament\Schemas\Schema;

class EditProfile extends \Filament\Auth\Pages\EditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                /** @phpstan-ignore-next-line */
                $this->getPasswordFormComponent()->helperText(app()->isProduction() ? __('organiser/pages/auth/register.form.password.helper_text') : null),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }
}
