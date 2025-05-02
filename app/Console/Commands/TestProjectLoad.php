<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;
use App\Models\User;

class TestProjectLoad extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:project-load';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test loading a project from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing project load functionality...');
        
        try {
            // Find the test user
            $user = User::where('email', 'test@example.com')->first();
            
            if (!$user) {
                $this->error('Test user not found. Please run test:project-save first.');
                return Command::FAILURE;
            }
            
            // Find the most recent project for this user
            $project = Project::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();
            
            if (!$project) {
                $this->error('No projects found for test user.');
                return Command::FAILURE;
            }
            
            $this->info("Found project: ID #{$project->id}, Name: {$project->name}");
            $this->info("Description: {$project->description}");
            $this->info("Main file ID: {$project->main_file_id}");
            
            // Load project files
            $files = $project->files()->get();
            
            $this->info("\nProject files:");
            $this->table(
                ['ID', 'Name', 'Path', 'Is Directory', 'Language', 'Parent Path'],
                $files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'name' => $file->name,
                        'path' => $file->path,
                        'is_directory' => $file->is_directory ? 'Yes' : 'No',
                        'language' => $file->language ?? 'N/A',
                        'parent_path' => $file->parent_path ?? 'N/A',
                    ];
                })
            );
            
            // Display content of main file
            $mainFile = $files->firstWhere('id', $project->main_file_id);
            if ($mainFile) {
                $this->info("\nContent of main file ({$mainFile->name}):");
                $this->line($mainFile->content);
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
