@extends('layouts.admin.app')

@section('title', 'Update AI Record')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-edit fs-24"></i></span>
                <span>Update {{ \Illuminate\Support\Str::singular($definition['label']) }} #{{ $record->id }}</span>
            </h1>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="alert alert-warning">
                    Changes affect AI analytics and audit history. Never enter secrets, payment details, or unredacted private data.
                </div>
                <form method="post" action="{{ route('admin.ai-management.update', [$type, $record->id]) }}">
                    @csrf
                    @method('put')
                    <div class="row">
                        @include('admin-views.ai-management.partials._fields', ['formId' => 'edit'])
                    </div>
                    <div class="btn--container justify-content-end">
                        <a href="{{ route('admin.ai-management.index', ['type' => $type]) }}" class="btn btn--reset">Back</a>
                        <button type="submit" class="btn btn--primary">Update record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
