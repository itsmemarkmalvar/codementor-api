<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LearningTopic;
use App\Models\LessonPlan;
use App\Models\LessonModule;

class BasicsCoreLessonPlansSeeder extends Seeder
{
	/**
	 * Seed core Java Basics lesson plans that might be missing in restored DBs.
	 */
	public function run(): void
	{
		$javaTopic = LearningTopic::where('title', 'Java Basics')->first();
		if (!$javaTopic) {
			$this->command->warn('Java Basics topic not found. Skipping BasicsCoreLessonPlansSeeder.');
			return;
		}

		$plans = [
			[
				'title' => 'Java Fundamentals',
				'description' => 'Step 1: Learn the fundamental building blocks of Java programming language.',
				'prerequisites' => null,
				'order_index' => 1,
			],
			[
				'title' => 'Java Control Flow',
				'description' => 'Step 2: Master control flow statements like conditionals and loops.',
				'prerequisites' => 'Java Fundamentals',
				'order_index' => 2,
			],
			[
				'title' => 'Java Object-Oriented Programming',
				'description' => 'Step 4: Understand object-oriented concepts like classes, objects, and inheritance.',
				'prerequisites' => 'Java Methods in Depth',
				'order_index' => 4,
			],
			[
				'title' => 'Java Exception Handling',
				'description' => 'Step 5: Master exception handling to build robust applications.',
				'prerequisites' => 'Java Object-Oriented Programming',
				'order_index' => 5,
			],
			[
				'title' => 'Java Data Structures',
				'description' => 'Step 7: Explore essential data structures and the Java Collections Framework.',
				'prerequisites' => 'Java Object-Oriented Programming',
				'order_index' => 7,
			],
		];

		foreach ($plans as $p) {
			$existing = LessonPlan::where('topic_id', $javaTopic->id)->where('title', $p['title'])->first();
			if ($existing) {
				$this->command->info("Lesson plan '{$p['title']}' already exists. Skipping.");
				continue;
			}

			$plan = LessonPlan::create([
				'topic_id' => $javaTopic->id,
				'title' => $p['title'],
				'description' => $p['description'],
				'learning_objectives' => $p['description'],
				'prerequisites' => $p['prerequisites'],
				'difficulty_level' => 1,
				'estimated_minutes' => 120,
				'is_published' => true,
			]);

			LessonModule::create([
				'lesson_plan_id' => $plan->id,
				'title' => $p['title'].' Overview',
				'order_index' => $p['order_index'],
				'description' => $p['description'],
				'content' => '# '.$p['title']."\n\nContent coming soon.",
				'is_published' => true,
				'estimated_minutes' => 45,
			]);

			$plan->updateModulesCount();
			$this->command->info("Created core lesson plan '{$p['title']}' (ID: {$plan->id})");
		}
	}
}


