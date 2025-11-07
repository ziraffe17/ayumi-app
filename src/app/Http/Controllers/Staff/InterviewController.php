<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InterviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Interview::with(['user', 'staff']);

        if ($request->filled('q')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->q . '%');
            });
        }

        if ($request->filled('from')) {
            $query->where('interview_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('interview_date', '<=', $request->to);
        }

        $interviews = $query->latest('interview_date')->paginate(20);
        $users = User::where('is_active', true)->get();

        return view('staff.interviews.index', compact('interviews', 'users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'interview_date' => 'required|date',
            'summary_enc' => 'required|string',
            'detail_enc' => 'nullable|string',
            'next_action' => 'nullable|string',
        ]);

        $validated['staff_id'] = Auth::guard('staff')->id();

        Interview::create($validated);

        return response()->json(['message' => '面談記録を作成しました']);
    }

    public function update(Request $request, Interview $interview)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'interview_date' => 'required|date',
            'summary_enc' => 'required|string',
            'detail_enc' => 'nullable|string',
            'next_action' => 'nullable|string',
        ]);

        $interview->update($validated);

        return response()->json(['message' => '面談記録を更新しました']);
    }

    public function destroy(Interview $interview)
    {
        $interview->delete();

        return response()->json(['message' => '面談記録を削除しました']);
    }
}