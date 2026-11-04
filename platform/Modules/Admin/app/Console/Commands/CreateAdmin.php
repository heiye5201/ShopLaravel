<?php

namespace Modules\Admin\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email} {--name=管理员}';

    protected $description = '交互式创建后台管理员（密码隐藏输入，不写入命令历史）';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name'),
            'email' => $this->argument('email'),
            'password' => $this->secret('请输入密码（至少 8 位）'),
            'password_confirmation' => $this->secret('请再次输入密码'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create(collect($validator->validated())->except('password_confirmation')->all());
        $this->info('管理员创建成功，请访问 /admin 登录。');

        return self::SUCCESS;
    }
}
