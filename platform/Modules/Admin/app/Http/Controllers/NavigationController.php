<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Models\ShopMenu;

class NavigationController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->response(ShopMenu::query()->where('placement', 'sidebar')->first()?->items ?? config('admin.navigation'));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'groups' => ['required', 'array', 'list', 'size:3'],
            'groups.*' => ['required', 'array:id,label,items'],
            'groups.*.id' => ['required', 'string', 'distinct'],
            'groups.*.label' => ['required', 'string', 'max:100', 'regex:/\S/u'],
            'groups.*.items' => ['required', 'array', 'list', 'max:20'],
            'groups.*.items.*' => ['required', 'array:path,title,icon,enabled,children'],
            'groups.*.items.*.path' => ['required', 'string'],
            'groups.*.items.*.title' => ['required', 'string', 'max:100', 'regex:/\S/u'],
            'groups.*.items.*.enabled' => ['required', 'boolean'],
            'groups.*.items.*.icon' => ['sometimes', 'string'],
            'groups.*.items.*.children' => ['sometimes', 'array', 'list', 'max:20'],
            'groups.*.items.*.children.*' => ['required', 'array:path,title,enabled'],
            'groups.*.items.*.children.*.path' => ['required', 'string'],
            'groups.*.items.*.children.*.title' => ['required', 'string', 'max:100', 'regex:/\S/u'],
            'groups.*.items.*.children.*.enabled' => ['required', 'boolean'],
        ]);
        $defaults = collect(config('admin.navigation'))->keyBy('id');
        $groups = [];
        foreach ($data['groups'] as $group) {
            $default = $defaults->get($group['id']);
            if (! $default) {
                $this->invalid();
            }
            $groups[] = ['id' => $group['id'], 'label' => trim($group['label']), 'items' => $this->normalize($group['items'], $default['items'])];
        }
        ShopMenu::query()->updateOrCreate(['placement' => 'sidebar'], ['name' => '后台导航', 'items' => $groups]);

        return $this->response($groups);
    }

    private function normalize(array $items, array $defaults): array
    {
        $paths = array_column($items, 'path');
        $expected = array_column($defaults, 'path');
        sort($paths);
        sort($expected);
        if ($paths !== $expected) {
            $this->invalid();
        }
        $known = collect($defaults)->keyBy('path');
        $result = [];
        foreach ($items as $item) {
            $default = $known->get($item['path']);
            if (in_array($item['path'], ['/shop', '/shop/menus'], true) && ! $item['enabled']) {
                throw ValidationException::withMessages(['groups' => '请保留店铺管理及菜单栏入口，以便继续管理导航。']);
            }
            $row = ['path' => $item['path'], 'title' => trim($item['title']), 'enabled' => (bool) $item['enabled']];
            if (isset($default['icon'])) {
                $row['icon'] = $default['icon'];
            }
            if (isset($default['children'])) {
                $row['children'] = $this->normalize($item['children'] ?? [], $default['children']);
            } elseif (isset($item['children'])) {
                $this->invalid();
            }
            $result[] = $row;
        }

        return $result;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['groups' => '栏目结构无效，请重新加载；请使用显示开关管理现有栏目。']);
    }

    private function response(array $groups): JsonResponse
    {
        return response()->json(['data' => $groups, 'defaults' => config('admin.navigation')])->header('Cache-Control', 'no-store');
    }
}
