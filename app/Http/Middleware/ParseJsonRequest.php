<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ParseJsonRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if request is JSON
        if ($request->header('Content-Type') === 'application/json' && $request->getContent()) {
            $jsonData = json_decode($request->getContent(), true);
            
            if ($jsonData && is_array($jsonData)) {
                // Merge JSON data into request
                $request->merge($jsonData);
                
                // Also set the request input directly
                $request->replace($jsonData);
                
                // Debug logging
                \Log::info('JSON middleware processed', [
                    'content_type' => $request->header('Content-Type'),
                    'raw_content' => $request->getContent(),
                    'parsed_data' => $jsonData,
                    'request_all' => $request->all(),
                ]);
            }
        }

        return $next($request);
    }
}
