// Check active sessions
echo "\n5. Active Sessions:\n";
$activeSessions = SplitScreenSession::whereNull('ended_at')->count();
echo "   Active SplitScreenSessions: " . $activeSessions . "\n";

// Note: PreservedSessions don't have current_state column, so we'll skip this check
echo "   Active PreservedSessions: Check not available (no current_state column)\n";
