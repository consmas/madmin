<?php

return [
    'name' => 'AI',
    'default_provider' => env('AI_PROVIDER', 'openai'),
    'default_model' => env('AI_DEFAULT_MODEL', 'gpt-4o-mini'),
    'request_timeout' => (int) env('AI_REQUEST_TIMEOUT', 30),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 700),
    'enable_ai_chat' => filter_var(env('AI_CHAT_ENABLED', true), FILTER_VALIDATE_BOOL),
    'enable_ai_search' => filter_var(env('AI_SEARCH_ENABLED', true), FILTER_VALIDATE_BOOL),
    'monthly_budget_limit' => env('AI_MONTHLY_BUDGET_LIMIT'),
    'per_user_daily_limit' => env('AI_PER_USER_DAILY_LIMIT'),
    'log_prompts' => filter_var(env('AI_LOG_PROMPTS', true), FILTER_VALIDATE_BOOL),
    'redact_sensitive_data' => filter_var(env('AI_REDACT_SENSITIVE_DATA', true), FILTER_VALIDATE_BOOL),
];
