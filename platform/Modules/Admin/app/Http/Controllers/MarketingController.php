<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Http\Requests\CampaignRequest;
use Modules\Admin\Http\Resources\CampaignResource;
use Modules\Admin\Models\MarketingCampaign;
use Modules\Admin\Services\MarketingGenerator;

class MarketingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'paused'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return CampaignResource::collection(MarketingCampaign::query()
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')->paginate(10)->withQueryString());
    }

    public function store(CampaignRequest $request): JsonResponse
    {
        return CampaignResource::make(MarketingCampaign::create($request->validated())->refresh())
            ->response()->setStatusCode(201);
    }

    public function update(CampaignRequest $request, MarketingCampaign $campaign): CampaignResource
    {
        $campaign = DB::transaction(function () use ($request, $campaign): MarketingCampaign {
            $locked = MarketingCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            abort_if($locked->status === 'published', 409, '请先暂停活动，再编辑内容。');
            $locked->update($request->validated());

            return $locked;
        });

        return CampaignResource::make($campaign);
    }

    public function status(Request $request, MarketingCampaign $campaign): CampaignResource
    {
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'paused'])]]);
        $campaign = DB::transaction(function () use ($data, $campaign): MarketingCampaign {
            $locked = MarketingCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if ($data['status'] === 'published') {
                $available = Product::query()->whereIn('id', $locked->product_ids)->where('is_available', true)->count();
                if ($available !== count($locked->product_ids)) {
                    throw ValidationException::withMessages(['product_ids' => '发布前请确保所选商品全部存在且已上架。']);
                }
                if ($locked->ends_at?->isPast()) {
                    throw ValidationException::withMessages(['ends_at' => '活动已过期，请先调整结束时间。']);
                }
            }
            $locked->status = $data['status'];
            $locked->save();

            return $locked;
        });

        return CampaignResource::make($campaign);
    }

    public function config(MarketingGenerator $generator): JsonResponse
    {
        return response()->json(['data' => [
            'configured' => $generator->configured(),
            'model' => $generator->configured() ? config('admin.ai.model') : null,
        ]]);
    }

    public function generate(Request $request, MarketingGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:6'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->where('is_available', true)],
            'goal' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json(['data' => $generator->generate($data['product_ids'], $data['goal'])]);
    }
}
