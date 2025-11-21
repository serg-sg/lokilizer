<?php

use PrinsFrank\Standards\Language\LanguageAlpha2;
use XAKEPEHOK\Lokilizer\Apps\Portal\Components\RouteUri;
use XAKEPEHOK\Lokilizer\Models\Project\Components\Role\Permission;
use XAKEPEHOK\Lokilizer\Models\Project\Components\UserRole;
use League\Plates\Template\Template;
use Slim\Http\ServerRequest;
use XAKEPEHOK\Lokilizer\Services\InviteService\Components\Invite;

/** @var Template $this */
/** @var ServerRequest $request */
/** @var RouteUri $route */
/** @var LanguageAlpha2[] $languages */
/** @var Invite[] $invites */
/** @var UserRole[] $users */

$this->layout('project_layout', ['request' => $request, 'title' => 'Users']) ?>

<table class="table datatables">
    <thead>
    <tr>
        <th scope="col">
            <a
                    title="Generate invite link"
                    class="btn btn-outline-success"
                    href="<?=$route('users/invite')?>">➕</a>
        </th>
        <th scope="col">User</th>
        <th scope="col">Role</th>
        <?php foreach ($languages as $language): ?>
            <td><?= $this->e($language->name) ?></td>
        <?php endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($invites as $invite): ?>
        <tr>
            <td>
                <form method="POST" class="submit-confirmation" data-confirmation="Are you sure you want delete this invite?">
                    <button
                            type="submit"
                            name="revoke"
                            value="<?=$invite->id?>"
                            class="btn btn-outline-danger"
                    >🗑️</button>
                </form>
            </td>
            <th scope="row">
                <a href="<?=$route("invite/{$invite->id}")?>">
                    <?=$invite->id?>
                </a>
                <br>
                <span class="fw-normal text-secondary">
                    <?= $this->insert('widgets/_timeago', ['datetime' => $invite->expireAt()]) ?>
                </span>
            </th>
            <td>
                <?= $this->e($invite->role->name) ?>
            </td>
            <?php foreach ($languages as $language): ?>
                <td><?= ($invite->role->can(Permission::MANAGE_LANGUAGES) || in_array($language, $invite->languages)) ? '✅' : '' ?></td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    <?php foreach ($users as $userRole): ?>
        <tr>
            <td>
                <?php $user = $userRole->getUser(); ?>
                <?php if ($user): ?>
                    <a class="btn btn-outline-primary" href="<?= $route("users/{$user->id()}") ?>">⚙️</a>
                <?php else: ?>
                    <!-- ВАЖНО: Используем id из Reference -->
                    <a class="btn btn-outline-primary" href="<?= $route("users/{$userRole->user->id()->get()}") ?>">⚙️</a>
                <?php endif; ?>
            </td>
            <th scope="row">
                <?php $user = $userRole->getUser(); ?>
                <?php if ($user): ?>
                    <?= $this->e($user->getName()) ?>
                    <br>
                    <span class="text-secondary fw-normal">
                        <?= $this->e($user->getEmail()) ?>
                    </span>
                <?php else: ?>
                    <em>Deleted User</em> <!-- Отображаем специальное сообщение -->
                    <br>
                    <span class="text-secondary fw-normal">
                        ID: <?=$this->e($userRole->user->id()->get())?> <!-- Показываем ID, если имя/емейл недоступны -->
                    </span>
                <?php endif; ?>
            </th>
            <td>
                <?= $this->e($userRole->role->name) ?>
            </td>
            <?php foreach ($languages as $language): ?>
                <?php $user = $userRole->getUser(); ?>
                <?php if ($user): ?>
                    <td><?= ($userRole->can(Permission::MANAGE_LANGUAGES) || in_array($language, $userRole->languages)) ? '✅' : '' ?></td>
                <?php else: ?>
                    <td></td> <!-- Оставляем ячейку пустой или ставим значок, если пользователь удалён -->
                <?php endif; ?>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>