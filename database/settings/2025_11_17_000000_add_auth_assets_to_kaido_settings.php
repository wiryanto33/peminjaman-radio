<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('KaidoSetting.auth_logo_path', null);
        $this->migrator->add('KaidoSetting.auth_background_path', null);
        $this->migrator->add('KaidoSetting.auth_card_opacity', 90);
    }
};
