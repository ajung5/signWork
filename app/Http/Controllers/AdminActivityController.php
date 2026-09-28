<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminActivityController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['outcome' => ['nullable', 'in:success,error'], 'event' => ['nullable', 'string', 'max:100']]);
        $logs = ActivityLog::with('user')->when($data['outcome'] ?? null, fn ($q, $value) => $q->where('outcome', $value))
            ->when($data['event'] ?? null, fn ($q, $value) => $q->where('event', $value))->latest('id')->paginate(30)->withQueryString();

        return view('admin.activity.index', compact('logs'));
    }
}
