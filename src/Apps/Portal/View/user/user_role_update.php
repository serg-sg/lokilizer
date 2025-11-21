<?php

use League\Plates\Template\Template;
use PrinsFrank\Standards\Language\LanguageAlpha2;
use Slim\Http\ServerRequest;
use XAKEPEHOK\Lokilizer\Models\Project\Components\Role\Role;
use XAKEPEHOK\Lokilizer\Models\User\User;
use XAKEPEHOK\Lokilizer\Apps\Portal\Components\RouteUri;

/** @var Template $this */
/** @var ServerRequest $request */
/** @var User|null $user */ // <-- Тип теперь nullable
/** @var string $role */
/** @var LanguageAlpha2[] $languages */
/** @var array $selectedLanguages */ // Тип может быть не только LanguageAlpha2[], но и []
/** @var string $error */
/** @var bool $userNotFound */
/** @var string|null $userId */

// Определяем заголовок в зависимости от наличия пользователя
$title = $user ? $user->getName() : ($userNotFound ? 'Orphaned User Role' : 'User'); // Резервный вариант

$this->layout('project_layout', ['request' => $request, 'title' => $title])
?>

<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-labelledby="confirmSubmitModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmSubmitModalLabel">Confirm Action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="confirmSubmitMessage">Are you sure you want to perform this action?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmSubmitBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>

<div class="col mx-auto">
    <?php if ($userNotFound): ?>
        <!-- Сценарий: Пользователь не найден -->
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading">User Data Unavailable</h4>
            <p>The user associated with this role has been deleted from the system, but the role record still exists in the project.</p>
            <hr>
            <p class="mb-0">You can remove this orphaned role entry from the project using the button below.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Форма для удаления "осиротевшего" UserRole -->
        <form method="post" class="submit-confirmation-modal" data-confirmation-message="Are you sure you want to remove this orphaned user role for ID <?=$this->e($userId ?? 'N/A')?>? This cannot be undone.">
            <input type="hidden" name="delete" value="1">
            <button class="btn btn-danger py-2" type="submit">Remove Orphaned Role</button>
        </form>

        <!-- Кнопка "Назад" к списку пользователей -->
        <div class="mt-3">
            <a href="<?= (new RouteUri($request))('users') ?>" class="btn btn-secondary">Back to Users</a>
        </div>

    <?php else: ?>
        <!-- Сценарий: Пользователь существует (старая логика) -->
        <form id="user-role-update"  method="post">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="form-floating mb-3">
                <select class="form-select" id="role" name="role">
                    <?php foreach (Role::cases() as $case): ?>
                        <option value="<?= $this->e($case->value) ?>" <?= $case->value === $role ? 'selected' : '' ?>>
                            <?= $this->e($case->name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="role">Role</label>
            </div>

            <div class="mb-3">
                <?php foreach ($languages as $language): ?>
                    <div class="form-check">
                        <input
                                class="form-check-input"
                                type="checkbox"
                                name="selectedLanguages[]"
                                value="<?= $this->e($language->value) ?>"
                                id="lang-<?= $this->e($language->value) ?>"
                            <?=in_array($language, $selectedLanguages) ? 'checked' : ''?>
                        >
                        <label class="form-check-label" for="lang-<?= $this->e($language->value) ?>">
                            <?= $this->e($language->name) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>
        <div class="hstack gap-3">
            <button form="user-role-update" class="btn btn-primary py-2" type="submit">Save</button>
            <form method="post" class="submit-confirmation-modal" data-confirmation-message="Are you sure you want delete this user from project?">
                <input
                    type="hidden"
                    name="delete"
                    value="1">
                <button class="btn btn-danger py-2" type="submit">Delete user</button>
            </form>
        </div>

        <!-- Кнопка "Назад" к списку пользователей -->
        <div class="mt-3">
            <a href="<?= (new RouteUri($request))('users') ?>" class="btn btn-secondary">Back to Users</a>
        </div>
    <?php endif; ?>
</div>
