<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TestProjectSave extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:project-save';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test saving a project to database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing project save functionality...');
        
        try {
            DB::beginTransaction();
            
            // Create test user if not exists
            $user = User::firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'password' => Hash::make('password123'),
                ]
            );
            
            $this->info("Using user ID: {$user->id}");
            
            // Create a test project
            $project = Project::create([
                'user_id' => $user->id,
                'name' => 'Test Java Project',
                'description' => 'A project created for testing the save functionality',
                'metadata' => json_encode(['created_from' => 'test command']),
            ]);
            
            $this->info("Created project ID: {$project->id}");
            
            // Create root directory
            $rootDir = ProjectFile::create([
                'project_id' => $project->id,
                'name' => $project->name,
                'path' => '/',
                'is_directory' => true,
            ]);
            
            // Create src directory
            $srcDir = ProjectFile::create([
                'project_id' => $project->id,
                'name' => 'src',
                'path' => '/src',
                'is_directory' => true,
                'parent_path' => '/',
            ]);
            
            // Create Main.java file
            $mainFile = ProjectFile::create([
                'project_id' => $project->id,
                'name' => 'Main.java',
                'path' => '/src/Main.java',
                'content' => 'public class Main {
    public static void main(String[] args) {
        // Your code here
        System.out.println("Hello, Java!");
    }
}',
                'is_directory' => false,
                'language' => 'java',
                'parent_path' => '/src',
            ]);
            
            // Set main file
            $project->update(['main_file_id' => $mainFile->id]);
            
            DB::commit();
            
            $this->info("Successfully created project with main file: {$mainFile->id}");
            $this->info("Project has " . $project->files()->count() . " files");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
