#!/bin/bash
# Script to reset and re-seed lesson plans

echo "Resetting lesson plan data..."
php artisan db:seed --class=ResetLessonPlansSeeder

echo ""
echo "Re-seeding lesson plans..."
php artisan db:seed --class=LessonPlanSeeder

echo ""
echo "Done! Lesson plans have been reset and re-seeded." 