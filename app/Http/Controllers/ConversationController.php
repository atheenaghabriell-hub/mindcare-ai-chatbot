<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartConversationRequest;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(): View
    {
        $conversations = Auth::user()->conversations()
            ->withCount('messages')
            ->latest('last_message_at')
            ->paginate(15);

        return view('chat.index', compact('conversations'));
    }

    public function store(StartConversationRequest $request): RedirectResponse
    {
        $conversation = $request->user()->conversations()->create([
            'title' => $request->validated('title') ?: 'New conversation',
            'last_message_at' => now(),
        ]);

        return redirect()->route('conversations.show', $conversation);
    }

    public function show(Conversation $conversation): View
    {
        $this->authorize('view', $conversation);
        $messages = $conversation->messages()->oldest('id')->paginate(50);

        return view('chat.show', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);
        $conversation->delete();

        return redirect()->route('conversations.index')
            ->with('status', 'Conversation deleted.');
    }
}
