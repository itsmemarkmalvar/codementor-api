<?php

namespace Database\Seeders;

use App\Models\PracticeResource;
use App\Models\PracticeProblem;
use Illuminate\Database\Seeder;

class PracticeResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resources = [
            [
                'title' => 'Understanding Arrays in JavaScript',
                'description' => 'A comprehensive guide to arrays and array methods in JavaScript',
                'url' => 'https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array',
                'type' => 'documentation',
                'source' => 'MDN Web Docs',
                'is_premium' => false,
                'difficulty_level' => 'beginner',
                'estimated_time_minutes' => 30,
                'thumbnail_url' => 'https://example.com/thumbnails/arrays.jpg',
                'is_official' => true,
                'rating' => 4.8,
                'views' => 1250,
            ],
            [
                'title' => 'JavaScript Array Methods Every Developer Should Know',
                'description' => 'Learn about map, filter, reduce and other essential array methods',
                'url' => 'https://www.freecodecamp.org/news/javascript-array-methods/',
                'type' => 'article',
                'source' => 'freeCodeCamp',
                'is_premium' => false,
                'difficulty_level' => 'intermediate',
                'estimated_time_minutes' => 20,
                'thumbnail_url' => 'https://example.com/thumbnails/array-methods.jpg',
                'is_official' => false,
                'rating' => 4.6,
                'views' => 980,
            ],
            [
                'title' => 'JavaScript Data Structures: Objects and Arrays',
                'description' => 'A deep dive into JavaScript objects and arrays with practical examples',
                'url' => 'https://eloquentjavascript.net/04_data.html',
                'type' => 'documentation',
                'source' => 'Eloquent JavaScript',
                'is_premium' => false,
                'difficulty_level' => 'intermediate',
                'estimated_time_minutes' => 45,
                'thumbnail_url' => 'https://example.com/thumbnails/data-structures.jpg',
                'is_official' => true,
                'rating' => 4.9,
                'views' => 2150,
            ],
            [
                'title' => 'Mastering Regular Expressions in JavaScript',
                'description' => 'Learn how to use regular expressions effectively for string manipulation and validation',
                'url' => 'https://www.javascripttutorial.net/javascript-regex/',
                'type' => 'tutorial',
                'source' => 'JavaScript Tutorial',
                'is_premium' => false,
                'difficulty_level' => 'advanced',
                'estimated_time_minutes' => 60,
                'thumbnail_url' => 'https://example.com/thumbnails/regex.jpg',
                'is_official' => false,
                'rating' => 4.7,
                'views' => 1850,
            ],
            [
                'title' => 'Async JavaScript: From Callbacks to Promises to Async/Await',
                'description' => 'A comprehensive guide to asynchronous programming in JavaScript',
                'url' => 'https://tylermcginnis.com/async-javascript-from-callbacks-to-promises-to-async-await/',
                'type' => 'article',
                'source' => 'Tyler McGinnis',
                'is_premium' => true,
                'difficulty_level' => 'advanced',
                'estimated_time_minutes' => 90,
                'thumbnail_url' => 'https://example.com/thumbnails/async.jpg',
                'is_official' => false,
                'rating' => 4.9,
                'views' => 3200,
            ],
        ];

        foreach ($resources as $resource) {
            PracticeResource::create($resource);
        }

        // Link resources to practice problems
        $this->linkResourcesToProblems();
    }

    /**
     * Link resources to appropriate practice problems
     */
    private function linkResourcesToProblems(): void
    {
        // Get all problems and resources
        $problems = PracticeProblem::all();
        $resources = PracticeResource::all();
        
        if ($problems->isEmpty() || $resources->isEmpty()) {
            return;
        }

        // Example: Link array-related resources to array problems
        $arrayProblems = $problems->filter(function ($problem) {
            return stripos($problem->title, 'array') !== false || 
                   stripos($problem->description, 'array') !== false;
        });

        $arrayResources = $resources->filter(function ($resource) {
            return stripos($resource->title, 'array') !== false || 
                   stripos($resource->description, 'array') !== false;
        });

        foreach ($arrayProblems as $problem) {
            foreach ($arrayResources as $resource) {
                // Randomly assign relevance scores between 0.7 and 1.0
                $relevanceScore = rand(70, 100) / 100;
                $problem->resources()->attach($resource->id, ['relevance_score' => $relevanceScore]);
            }
        }

        // Link regex resources to string manipulation problems
        $regexResources = $resources->filter(function ($resource) {
            return stripos($resource->title, 'regex') !== false || 
                   stripos($resource->title, 'regular expression') !== false;
        });

        $stringProblems = $problems->filter(function ($problem) {
            return stripos($problem->title, 'string') !== false || 
                   stripos($problem->description, 'string') !== false ||
                   stripos($problem->description, 'text') !== false;
        });

        foreach ($stringProblems as $problem) {
            foreach ($regexResources as $resource) {
                $relevanceScore = rand(70, 100) / 100;
                $problem->resources()->attach($resource->id, ['relevance_score' => $relevanceScore]);
            }
        }

        // Link async resources to async-related problems
        $asyncResources = $resources->filter(function ($resource) {
            return stripos($resource->title, 'async') !== false || 
                   stripos($resource->description, 'promise') !== false;
        });

        $asyncProblems = $problems->filter(function ($problem) {
            return stripos($problem->title, 'async') !== false || 
                   stripos($problem->description, 'promise') !== false ||
                   stripos($problem->description, 'callback') !== false;
        });

        foreach ($asyncProblems as $problem) {
            foreach ($asyncResources as $resource) {
                $relevanceScore = rand(80, 100) / 100;
                $problem->resources()->attach($resource->id, ['relevance_score' => $relevanceScore]);
            }
        }
    }
} 