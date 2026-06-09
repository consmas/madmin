@extends('layouts.admin.app')

@section('title', 'AI Management')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-robot fs-24"></i></span>
                <span>AI Management</span>
            </h1>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h4>Phase 1 AI records</h4>
                <p class="mb-0 text-muted">
                    Review and manually maintain AI conversations, messages, search analytics, recommendations, customer
                    intents, and tool-call audit records. Do not enter passwords, payment details, API keys, or private tokens.
                </p>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h4 class="mb-1">AI Configuration</h4>
                    <p class="mb-0 text-muted fs-12">These database-backed settings override Phase 1 environment defaults.</p>
                </div>
                <a href="{{ route('admin.business-settings.openAI') }}" class="btn btn--secondary">
                    Manage OpenAI API credentials
                </a>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.ai-management.settings.update') }}">
                    @csrf
                    @method('put')
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="input-label">Provider</label>
                            <select name="default_provider" class="form-control">
                                <option value="openai" selected>OpenAI</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="input-label">Default model</label>
                            <input name="default_model" class="form-control" value="{{ old('default_model', $settings['default_model']) }}" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="input-label">Request timeout (seconds)</label>
                            <input type="number" name="request_timeout" min="5" max="120" class="form-control"
                                value="{{ old('request_timeout', $settings['request_timeout']) }}" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="input-label">Maximum response tokens</label>
                            <input type="number" name="max_tokens" min="100" max="4000" class="form-control"
                                value="{{ old('max_tokens', $settings['max_tokens']) }}" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="input-label">Monthly budget limit</label>
                            <input type="number" step="any" min="0" name="monthly_budget_limit" class="form-control"
                                value="{{ old('monthly_budget_limit', $settings['monthly_budget_limit']) }}">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="input-label">Per-user daily limit</label>
                            <input type="number" min="0" name="per_user_daily_limit" class="form-control"
                                value="{{ old('per_user_daily_limit', $settings['per_user_daily_limit']) }}">
                        </div>
                        @foreach ([
                            'enable_ai_chat' => 'Enable AI chat',
                            'enable_ai_search' => 'Enable AI search',
                            'log_prompts' => 'Store prompts',
                            'redact_sensitive_data' => 'Redact sensitive data',
                        ] as $setting => $label)
                            <div class="col-md-3 form-group">
                                <input type="hidden" name="{{ $setting }}" value="0">
                                <div class="custom-control custom-checkbox mt-4">
                                    <input type="checkbox" class="custom-control-input" id="setting-{{ $setting }}"
                                        name="{{ $setting }}" value="1" @checked(old($setting, $settings[$setting]))>
                                    <label class="custom-control-label" for="setting-{{ $setting }}">{{ $label }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="alert alert-warning">
                        Disabling redaction or storing prompts increases privacy risk. API keys remain managed on the protected
                        OpenAI configuration page and are never displayed here.
                    </div>
                    <div class="btn--container justify-content-end">
                        <button type="submit" class="btn btn--primary">Save AI configuration</button>
                    </div>
                </form>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3">
            @foreach ($definitions as $key => $item)
                <li class="nav-item">
                    <a class="nav-link {{ $type === $key ? 'active' : '' }}"
                        href="{{ route('admin.ai-management.index', ['type' => $key]) }}">{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h4 class="mb-1">Add {{ \Illuminate\Support\Str::singular($definition['label']) }}</h4>
                    <p class="mb-0 text-muted fs-12">Fields linked to existing records are validated before saving.</p>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.ai-management.store', $type) }}">
                    @csrf
                    <div class="row">
                        @include('admin-views.ai-management.partials._fields', ['formId' => 'create'])
                    </div>
                    <div class="btn--container justify-content-end">
                        <button type="reset" class="btn btn--reset">Reset</button>
                        <button type="submit" class="btn btn--primary">Save record</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <h4 class="mb-0">{{ $definition['label'] }} <span class="badge badge-soft-dark">{{ $records->total() }}</span></h4>
                <form class="search-form">
                    <input type="hidden" name="type" value="{{ $type }}">
                    <div class="input-group input--group">
                        <input type="search" name="search" value="{{ request('search') }}" class="form-control"
                            placeholder="Search {{ strtolower($definition['label']) }}">
                        <button class="btn btn--secondary" type="submit"><i class="tio-search"></i></button>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Summary</th>
                            <th>Created</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td>{{ $record->id }}</td>
                                <td>
                                    @foreach (array_slice($definition['searchable'], 0, 3) as $column)
                                        @if (filled($record->{$column}))
                                            <div><strong>{{ ucfirst(str_replace('_', ' ', $column)) }}:</strong>
                                                {{ \Illuminate\Support\Str::limit((string) $record->{$column}, 100) }}</div>
                                        @endif
                                    @endforeach
                                </td>
                                <td>{{ optional($record->created_at)->format('Y-m-d H:i') }}</td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn btn--primary btn-outline-primary"
                                            href="{{ route('admin.ai-management.edit', [$type, $record->id]) }}"
                                            title="Edit"><i class="tio-edit"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-4">No records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer border-0">
                {!! $records->links() !!}
            </div>
        </div>
    </div>
@endsection
