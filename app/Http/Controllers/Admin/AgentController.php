<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Brand;
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
                ->with(['brands' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'created_at'])
                ->map(fn (Agent $agent) => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'slug' => $agent->slug,
                    'brand_ids' => $agent->brands->pluck('id'),
                    'brands' => $agent->brands->map(fn (Brand $brand) => [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'color' => $brand->color,
                    ]),
                    'created_at' => $agent->created_at?->toIso8601String(),
                ]),
            'brands' => Brand::query()
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand_ids' => ['required', 'array', 'min:1'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
        ]);

        $agent = Agent::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        $agent->brands()->sync($data['brand_ids']);

        return redirect()->route('agents.index');
    }

    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand_ids' => ['required', 'array', 'min:1'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
        ]);

        $slug = $agent->slug;
        if ($data['name'] !== $agent->name) {
            $slug = $this->uniqueSlug($data['name'], $agent->id);
        }

        $agent->update([
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        $agent->brands()->sync($data['brand_ids']);

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
