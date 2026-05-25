<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Agents', [
            'agents' => Agent::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'brand', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
        ]);

        $slug = $this->uniqueSlug($data['name']);

        Agent::create([
            'name' => $data['name'],
            'brand' => $data['brand'],
            'slug' => $slug,
        ]);

        return redirect()->route('agents.index');
    }

    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
        ]);

        $slug = $agent->slug;
        if ($data['name'] !== $agent->name) {
            $slug = $this->uniqueSlug($data['name'], $agent->id);
        }

        $agent->update([
            'name' => $data['name'],
            'brand' => $data['brand'],
            'slug' => $slug,
        ]);

        return redirect()->route('agents.index');
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        $agent->delete();

        return redirect()->route('agents.index');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Agent::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
