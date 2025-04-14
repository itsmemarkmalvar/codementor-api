<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the learning sessions for the user.
     */
    public function learningSessions()
    {
        return $this->hasMany(LearningSession::class);
    }

    /**
     * Get the chat messages for the user.
     */
    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Get the code snippets for the user.
     */
    public function codeSnippets()
    {
        return $this->hasMany(CodeSnippet::class);
    }

    /**
     * Get the progress records for the user.
     */
    public function progress()
    {
        return $this->hasMany(UserProgress::class);
    }

    /**
     * Get the quiz attempts for the user.
     */
    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Get all completed quizzes for the user.
     */
    public function completedQuizzes()
    {
        return $this->quizAttempts()
                    ->where('passed', true)
                    ->whereNotNull('completed_at')
                    ->with('quiz')
                    ->get()
                    ->pluck('quiz')
                    ->unique('id');
    }

    /**
     * Get best quiz attempt for a specific quiz.
     * 
     * @param int $quizId
     * @return QuizAttempt|null
     */
    public function getBestQuizAttempt($quizId)
    {
        return $this->quizAttempts()
                    ->where('quiz_id', $quizId)
                    ->orderBy('score', 'desc')
                    ->first();
    }
}
