<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

class ProjectController extends Controller
{
    /**
     * Display a listing of the projects.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Determine if we should include files in the response
        $includeFiles = $request->has('include_files') && $request->include_files;
        
        \Log::info('Projects index method called', [
            'user_id' => $user->id,
            'include_files' => $includeFiles
        ]);
        
        // Query builder for projects
        $query = Project::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc');
            
        // Load files if requested
        if ($includeFiles) {
            $query->with('files');
        }
        
        $projects = $query->get();
        
        // Add file counts to each project
        foreach ($projects as $project) {
            // If files are loaded, use the collection length
            if ($includeFiles && $project->relationLoaded('files')) {
                $project->file_count = $project->files->count();
            } else {
                // Otherwise, perform a count query
                $project->file_count = ProjectFile::where('project_id', $project->id)->count();
            }
        }
        
        \Log::info('Returning projects with file counts', [
            'project_count' => $projects->count(),
            'projects' => $projects->map(function($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'file_count' => $p->file_count
                ];
            })
        ]);

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }

    /**
     * Store a new project.
     */
    public function store(Request $request)
    {
        // Enable query logging
        DB::enableQueryLog();
        
        // Add detailed logging for debugging
        \Log::debug('Project store method called with data:', [
            'request_data' => $request->all(),
            'files_count' => count($request->input('files', [])),
            'user_id' => Auth::check() ? Auth::id() : 'Not authenticated',
            'auth_header' => $request->hasHeader('Authorization') ? 'Present' : 'Missing',
            'auth_header_value' => $request->hasHeader('Authorization') ? substr($request->header('Authorization'), 0, 20) . '...' : 'None'
        ]);

        // First check authentication
        if (!Auth::check()) {
            \Log::error('Authentication failed for project creation', [
                'token' => $request->bearerToken() ? 'Present' : 'Missing',
                'auth_header' => $request->header('Authorization'),
                'headers' => $request->headers->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'error' => 'User is not authenticated'
            ], 401);
        }
        
        // Log authenticated user information
        \Log::info('User authenticated for project creation', [
            'user_id' => Auth::id(),
            'user_email' => Auth::user()->email
        ]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'main_file_id' => 'nullable|string',
            'files' => 'required|array',
            'files.*.name' => 'required|string|max:255',
            'files.*.path' => 'required|string|max:255',
            'files.*.content' => 'nullable|string',
            'files.*.is_directory' => 'required|boolean',
            'files.*.language' => 'nullable|string|max:50',
            'files.*.parent_path' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            \Log::error('Project validation failed:', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            
            // Create project
            $project = Project::create([
                'user_id' => Auth::id(),
                'name' => $request->name,
                'description' => $request->description,
                'main_file_id' => $request->main_file_id,
                'metadata' => $request->metadata ?? null,
            ]);

            // Get the files array from the request
            $files = $request->input('files', []);
            
            \Log::debug('Project created successfully:', [
                'project_id' => $project->id,
                'files_to_create' => count($files)
            ]);

            // Create project files
            foreach ($files as $fileData) {
                try {
                    \Log::debug('Creating file:', [
                        'name' => $fileData['name'],
                        'path' => $fileData['path'],
                        'is_directory' => $fileData['is_directory']
                    ]);
                    
                    // Explicitly set the project_id and handle content being null
                    $file = new ProjectFile([
                        'name' => $fileData['name'],
                        'path' => $fileData['path'],
                        'content' => isset($fileData['content']) ? $fileData['content'] : null,
                        'is_directory' => $fileData['is_directory'],
                        'language' => $fileData['language'] ?? null,
                        'parent_path' => $fileData['parent_path'] ?? null,
                    ]);
                    
                    // Explicitly associate with project
                    $file->project_id = $project->id;
                    $file->save();
                    
                    \Log::debug('Project file created:', [
                        'file_id' => $file->id,
                        'file_name' => $file->name,
                        'file_path' => $file->path,
                        'project_id' => $file->project_id
                    ]);
                } catch (\Exception $fileEx) {
                    \Log::error('Failed to create project file:', [
                        'error' => $fileEx->getMessage(),
                        'trace' => $fileEx->getTraceAsString(),
                        'file_data' => $fileData
                    ]);
                    throw $fileEx;
                }
            }

            // Count the files that were actually saved
            $fileCount = $project->files()->count();
            \Log::debug('Project files count after save:', ['count' => $fileCount]);
            \Log::debug('SQL Queries:', ['queries' => DB::getQueryLog()]);

            DB::commit();

            \Log::debug('Project and files saved successfully', [
                'project_id' => $project->id,
                'files_count' => $project->files()->count()
            ]);

            // Get the project with files to return in the response
            $project = Project::with('files')->find($project->id);
            $project->file_count = $project->files->count();

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully',
                'data' => $project
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Failed to create project:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'queries' => DB::getQueryLog()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified project with its files.
     */
    public function show($id)
    {
        $project = Project::with('files')->findOrFail($id);
        
        // Check if the authenticated user owns the project
        if ($project->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Always include file count
        $project->file_count = $project->files->count();
        
        \Log::debug('Project show - returning project details:', [
            'project_id' => $project->id,
            'file_count' => $project->file_count
        ]);

        return response()->json([
            'success' => true,
            'data' => $project
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        
        // Check if the authenticated user owns the project
        if ($project->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'main_file_id' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $project->update($request->only([
                'name', 
                'description', 
                'main_file_id',
                'metadata'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Project updated successfully',
                'data' => $project
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified project and its files.
     */
    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        
        // Check if the authenticated user owns the project
        if ($project->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            // Delete project (files will be deleted by the cascade)
            $project->delete();

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a file to a project.
     */
    public function addFile(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        
        // Check if the authenticated user owns the project
        if ($project->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'path' => 'required|string|max:255',
            'content' => 'nullable|string',
            'is_directory' => 'required|boolean',
            'language' => 'nullable|string|max:50',
            'parent_path' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $project->files()->create([
                'name' => $request->name,
                'path' => $request->path,
                'content' => $request->content ?? null,
                'is_directory' => $request->is_directory,
                'language' => $request->language ?? null,
                'parent_path' => $request->parent_path ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File added successfully',
                'data' => $file
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a file in a project.
     */
    public function updateFile(Request $request, $projectId, $fileId)
    {
        $project = Project::findOrFail($projectId);
        
        // Check if the authenticated user owns the project
        if ($project->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $file = ProjectFile::where('project_id', $projectId)
            ->where('id', $fileId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'path' => 'sometimes|required|string|max:255',
            'content' => 'nullable|string',
            'is_directory' => 'sometimes|boolean',
            'language' => 'nullable|string|max:50',
            'parent_path' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file->update($request->only([
                'name',
                'path',
                'content',
                'is_directory',
                'language',
                'parent_path'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'File updated successfully',
                'data' => $file
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a file from a project.
     */
    public function deleteFile($projectId, $fileId)
    {
        $project = Project::findOrFail($projectId);
        
        // Check if the authenticated user owns the project
        if ($project->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $file = ProjectFile::where('project_id', $projectId)
            ->where('id', $fileId)
            ->firstOrFail();

        try {
            $file->delete();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export a project as a ZIP file.
     */
    public function export($id)
    {
        $project = Project::with('files')->findOrFail($id);
        
        // Check if the authenticated user owns the project
        if ($project->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            // Create a temporary file for the ZIP
            $zipFileName = 'project_' . $id . '.zip';
            $zipFilePath = storage_path('app/temp/' . $zipFileName);
            
            // Ensure the temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }
            
            // Create the ZIP file
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("Cannot create ZIP file");
            }

            // Get all non-directory files
            $files = $project->files()->where('is_directory', false)->get();
            
            // Add files to the ZIP
            foreach ($files as $file) {
                // Clean the path to avoid issues with leading slashes
                $filePath = ltrim($file->path, '/');
                $zip->addFromString($filePath, $file->content ?? '');
            }
            
            $zip->close();
            
            // Return the ZIP file for download
            return response()->download($zipFilePath, $project->name . '.zip')
                ->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import a project from a ZIP file.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'zip_file' => 'required|file|mimes:zip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            
            // Create the project
            $project = Project::create([
                'user_id' => Auth::id(),
                'name' => $request->name,
                'description' => $request->description,
            ]);

            // Store the uploaded ZIP file
            $zipFile = $request->file('zip_file');
            $zipFilePath = $zipFile->store('temp');
            $fullZipPath = storage_path('app/' . $zipFilePath);
            
            // Extract and process the ZIP file
            $zip = new ZipArchive();
            if ($zip->open($fullZipPath) !== true) {
                throw new \Exception("Cannot open ZIP file");
            }
            
            $directories = [];
            $mainFileId = null;
            
            // First pass: create directories
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $filename = $this->normalizeFilePath($filename);
                
                // Skip if the entry is macOS specific
                if (strpos($filename, '__MACOSX') === 0 || strpos($filename, '.DS_Store') !== false) {
                    continue;
                }
                
                // Create directories
                $pathParts = explode('/', $filename);
                $currentPath = '';
                
                for ($j = 0; $j < count($pathParts) - 1; $j++) {
                    $part = $pathParts[$j];
                    if (empty($part)) continue;
                    
                    $currentPath .= '/' . $part;
                    
                    if (!in_array($currentPath, $directories)) {
                        $directories[] = $currentPath;
                        
                        // Get parent path
                        $parentPath = $j > 0 ? '/' . implode('/', array_slice($pathParts, 0, $j)) : null;
                        
                        $project->files()->create([
                            'name' => $part,
                            'path' => $currentPath,
                            'is_directory' => true,
                            'parent_path' => $parentPath,
                        ]);
                    }
                }
            }
            
            // Second pass: create files
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $filename = $this->normalizeFilePath($filename);
                
                // Skip if the entry is a directory or macOS specific
                if (substr($filename, -1) === '/' || 
                    strpos($filename, '__MACOSX') === 0 || 
                    strpos($filename, '.DS_Store') !== false) {
                    continue;
                }
                
                // Get file content
                $content = $zip->getFromIndex($i);
                
                // Determine file language
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $language = $this->getLanguageFromExtension($extension);
                
                // Get parent path
                $parentPath = dirname($filename);
                if ($parentPath === '.') {
                    $parentPath = null;
                } else {
                    $parentPath = '/' . $parentPath;
                }
                
                // Create file record
                $file = $project->files()->create([
                    'name' => basename($filename),
                    'path' => '/' . $filename,
                    'content' => $content,
                    'is_directory' => false,
                    'language' => $language,
                    'parent_path' => $parentPath,
                ]);
                
                // If this is a potential main file, save its ID
                if ($language === 'java' && $mainFileId === null) {
                    // Look for a file named Main.java or with 'main' method
                    if (basename($filename) === 'Main.java' || 
                        strpos($content, 'public static void main') !== false) {
                        $mainFileId = $file->id;
                    }
                }
            }
            
            $zip->close();
            
            // Delete the temporary ZIP file
            Storage::delete($zipFilePath);
            
            // Update the project with the main file ID
            if ($mainFileId) {
                $project->update(['main_file_id' => $mainFileId]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Project imported successfully',
                'data' => $project->load('files')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to import project',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Helper function to normalize file paths from ZIP files.
     */
    private function normalizeFilePath($path)
    {
        // Remove any leading directory if exists
        $path = ltrim($path, '/');
        
        // Handle Windows-style paths
        $path = str_replace('\\', '/', $path);
        
        return $path;
    }
    
    /**
     * Helper function to determine the language from a file extension.
     */
    private function getLanguageFromExtension($extension)
    {
        $languageMap = [
            'java' => 'java',
            'txt' => 'plaintext',
            'md' => 'markdown',
            'json' => 'json',
            'xml' => 'xml',
            'properties' => 'properties',
            'html' => 'html',
            'css' => 'css',
            'js' => 'javascript',
        ];
        
        return $languageMap[strtolower($extension)] ?? null;
    }

    /**
     * Store a new project without authentication (FOR TESTING ONLY - REMOVE IN PRODUCTION)
     */
    public function testStore(Request $request)
    {
        // Enable query logging
        DB::enableQueryLog();
        
        \Log::debug('TEST STORE - Project creation test called with data:', [
            'request_data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'main_file_id' => 'nullable|string',
            'files' => 'required|array',
            'files.*.name' => 'required|string|max:255',
            'files.*.path' => 'required|string|max:255',
            'files.*.content' => 'nullable|string',
            'files.*.is_directory' => 'required|boolean',
            'files.*.language' => 'nullable|string|max:50',
            'files.*.parent_path' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            \Log::error('TEST STORE - Project validation failed:', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            
            // Create project - hardcode user_id to 1 for testing
            $project = Project::create([
                'user_id' => 1, // Hardcoded for testing
                'name' => $request->name,
                'description' => $request->description,
                'main_file_id' => $request->main_file_id,
                'metadata' => $request->metadata ?? null,
            ]);

            \Log::debug('TEST STORE - Project created successfully:', [
                'project_id' => $project->id,
                'files_to_create' => count($request->files)
            ]);

            // Create project files
            foreach ($request->files as $fileData) {
                try {
                    $file = new ProjectFile([
                        'name' => $fileData['name'],
                        'path' => $fileData['path'],
                        'content' => $fileData['content'] ?? null,
                        'is_directory' => $fileData['is_directory'],
                        'language' => $fileData['language'] ?? null,
                        'parent_path' => $fileData['parent_path'] ?? null,
                    ]);
                    
                    // Explicitly set project_id to ensure relationship
                    $file->project_id = $project->id;
                    $file->save();
                    
                    \Log::debug('TEST STORE - Project file created:', [
                        'file_id' => $file->id,
                        'file_name' => $file->name,
                        'file_path' => $file->path,
                        'project_id' => $file->project_id
                    ]);
                } catch (\Exception $fileEx) {
                    \Log::error('TEST STORE - Failed to create project file:', [
                        'error' => $fileEx->getMessage(),
                        'trace' => $fileEx->getTraceAsString(),
                        'file_data' => $fileData
                    ]);
                    throw $fileEx;
                }
            }

            // Count the files that were actually saved
            $fileCount = $project->files()->count();
            \Log::debug('TEST STORE - Project files count after save:', [
                'count' => $fileCount,
                'queries' => DB::getQueryLog()
            ]);

            DB::commit();

            \Log::debug('TEST STORE - Project and files saved successfully', [
                'project_id' => $project->id,
                'files_count' => $project->files()->count()
            ]);

            // Get the project with files to return in the response
            $project = Project::with('files')->find($project->id);
            $project->file_count = $project->files->count();

            return response()->json([
                'success' => true,
                'message' => 'Test project created successfully',
                'data' => $project
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('TEST STORE - Failed to create project:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'queries' => DB::getQueryLog()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create test project',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
