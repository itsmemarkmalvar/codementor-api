# How to Switch to Gemini 2.5 Flash-Lite

If you want to reduce quota usage and get faster responses, you can switch to the lighter model.

## Steps:

1. Open `codementor-api/.env`
2. Change line 64 from:
   ```
   GEMINI_MODEL=gemini-2.5-flash
   ```
   To:
   ```
   GEMINI_MODEL=gemini-2.5-flash-lite
   ```

3. Do the same in `codementor-api/.env.production`

4. Clear cache:
   ```bash
   cd codementor-api
   php artisan config:clear
   php artisan cache:clear
   ```

## Benefits:
- 40% faster responses
- Lower token usage (saves quota)
- Still excellent quality for tutoring

## When to use flash-lite vs regular flash:
- **flash-lite**: Simple explanations, code examples, Q&A (most tutoring use cases)
- **flash**: Complex reasoning, long detailed explanations, advanced topics

