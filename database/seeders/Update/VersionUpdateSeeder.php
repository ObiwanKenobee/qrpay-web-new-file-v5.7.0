<?php

namespace Database\Seeders\Update;

use Database\Seeders\Admin\EmailTemplateSeeder;
use Database\Seeders\Admin\SmsTemplateSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class VersionUpdateSeeder extends Seeder
{
    /**
     * Files whose name case changed (old path => new path).
     * Linux servers are case sensitive: the class must be in a file with the exact same name (PSR-4).
     */
    const RENAME_FILES = [
        'app/Http/Helpers/Api/helpers.php' => 'app/Http/Helpers/Api/Helpers.php',
    ];

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->renameFiles();

        //version Update Seeders
        $this->call([
            AppSettingsSeeder::class,
            BasicSettingsSeeder::class,
            VirtualApiSeeder::class,
            UpdatePaymentGatewaySeeder::class,
            DashboardStatsSeeder::class,
            EmailTemplateSeeder::class,
            SmsTemplateSeeder::class,
        ]);
    }

    /**
     * Rename old file names to the new case with the file:rename command.
     * Only when the file on disk still has the old name (checked by the real directory listing,
     * so it also works on case-insensitive systems where file_exists() matches both names).
     */
    protected function renameFiles(): void
    {
        foreach (self::RENAME_FILES as $old_path => $new_path) {
            $directory = base_path(dirname($old_path));
            // folder or file not there (already renamed / removed): nothing to do, no error
            if (!is_dir($directory) || !in_array(basename($old_path), @scandir($directory) ?: [], true)) continue;

            try {
                Artisan::call('file:rename', ['oldPath' => $old_path, 'newPath' => $new_path]);
                $this->command?->info(trim(Artisan::output()));
            } catch (\Throwable $e) {
                $this->command?->warn("Rename failed ({$old_path}): " . $e->getMessage());
            }
        }
    }

}