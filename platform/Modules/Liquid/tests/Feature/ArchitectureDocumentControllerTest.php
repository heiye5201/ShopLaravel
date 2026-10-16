<?php

namespace Modules\Liquid\Tests\Feature;

use Tests\TestCase;

class ArchitectureDocumentControllerTest extends TestCase
{
    public function test_renders_the_admin_graphql_architecture_document(): void
    {
        $this->assertSame(
            '/liquid/architecture/admin-graphql-data-access-foundation',
            route('liquid.architecture.admin-graphql-data-access', [], false),
        );

        $response = $this->get(route('liquid.architecture.admin-graphql-data-access'));

        $response
            ->assertOk()
            ->assertSeeText('Admin GraphQL 数据获取底座设计思考')
            ->assertSeeText('GraphQL Object 和 Liquid Drop 都不是数据源')
            ->assertSeeText('术语说明')
            ->assertSeeText('自动折扣只读 Demo')
            ->assertSeeText('AutomaticDiscountReader')
            ->assertSeeText('目标数据获取架构')
            ->assertSeeText('Liquid Lab 首页');
    }
}
