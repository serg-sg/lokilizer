<?php

namespace XAKEPEHOK\Lokilizer\Apps\Portal\Actions\Glossary;

use DiBify\DiBify\Manager\ModelManager;
use DiBify\DiBify\Manager\Transaction;
use JsonException;
use League\Plates\Engine;
use MongoDB\Driver\Exception\BulkWriteException;
use PrinsFrank\Standards\Language\LanguageAlpha2;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;
use XAKEPEHOK\Lokilizer\Apps\Portal\Components\ApiRuntimeException;
use XAKEPEHOK\Lokilizer\Apps\Portal\Components\RenderAction;
use XAKEPEHOK\Lokilizer\Apps\Portal\Components\RouteUri;
use XAKEPEHOK\Lokilizer\Components\Current;
use XAKEPEHOK\Lokilizer\Models\Glossary\Db\Storage\GlossaryRepo;
use XAKEPEHOK\Lokilizer\Models\Glossary\GlossaryItem;
use XAKEPEHOK\Lokilizer\Models\Glossary\GlossaryPhrase;
use XAKEPEHOK\Lokilizer\Models\Project\Components\Role\Permission;

class GlossaryImportAction extends RenderAction
{
    public function __construct(
        private GlossaryRepo $glossaryRepo,
        private ModelManager $modelManager,
        Engine $renderer
    )
    {
        parent::__construct($renderer);
    }

    public function __invoke(Request $request, Response $response): Response
    {
        // Проверяем права
        Current::guard(Permission::MANAGE_GLOSSARY);

        // Получаем ID глоссария из URL
        $glossaryId = $request->getAttribute('id');
        $glossary = $this->glossaryRepo->findById($glossaryId);

        $error = '';
        if ($request->isPost()) {
            try {
                /** @var \Slim\Http\UploadedFile $file */
                $file = $request->getUploadedFiles()['file'] ?? null;
                if (is_null($file)) {
                    throw new \RuntimeException('No file uploaded');
                }

                if ($file->getError() !== UPLOAD_ERR_OK) {
                    throw new \RuntimeException('File uploading error: ' . $file->getError());
                }

                $json = $file->getStream()->getContents();

                // Удаляем UTF-8 BOM, если он присутствует
                if (str_starts_with($json, "\xEF\xBB\xBF")) {
                    $json = substr($json, 3);
                }

                try {
                    $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new \RuntimeException('Invalid JSON format in the uploaded file');
                }

                // Валидация структуры данных - поддерживаем оба формата
                if (isset($data['glossary']) && is_array($data['glossary'])) {
                    // Формат export из jsonSerialize
                    $itemsData = $data['glossary'];
                    if (isset($data['summary'])) {
                        $glossary->setSummary($data['summary']);
                    }
                } elseif (isset($data['items']) && is_array($data['items'])) {
                    // Альтернативный формат с "items"
                    $itemsData = $data['items'];
                    if (isset($data['summary'])) {
                        $glossary->setSummary($data['summary']);
                    }
                } else {
                    // Предполагаем, что весь JSON - это массив элементов
                    if (!is_array($data)) {
                        throw new \RuntimeException('JSON structure is invalid. Expected array or object with glossary/summary keys.');
                    }
                    $itemsData = $data;
                }

                // Получаем основной язык проекта
                $projectPrimaryLanguage = Current::getProject()->getPrimaryLanguage();

                // Собираем новые элементы глоссария
                $items = [];
                foreach ($itemsData as $itemData) {
                    if (!is_array($itemData)) {
                        continue;
                    }

                    // Поддержка альтернативного формата с primary + translations
                    if (isset($itemData['primary']) && isset($itemData['translations']) && is_array($itemData['translations'])) {
                        $primaryData = $itemData['primary'];
                        if (!isset($primaryData['language']) || !isset($primaryData['phrase'])) {
                            continue;
                        }

                        $jsonPrimaryLangCode = $primaryData['language'];
                        $jsonPrimaryLanguage = LanguageAlpha2::tryFrom($jsonPrimaryLangCode);
                        if (!$jsonPrimaryLanguage || $jsonPrimaryLanguage !== $projectPrimaryLanguage) {
                            continue; // Пропускаем, если язык не совпадает
                        }

                        $primaryPhrase = new GlossaryPhrase(
                            $projectPrimaryLanguage,
                            $primaryData['phrase']
                        );

                        $description = $itemData['description'] ?? '';

                        $translations = [];
                        foreach ($itemData['translations'] as $translationData) {
                            if (!isset($translationData['language']) || !isset($translationData['phrase'])) {
                                continue;
                            }

                            $language = LanguageAlpha2::tryFrom($translationData['language']);
                            if (!$language || $language === $projectPrimaryLanguage) {
                                continue;
                            }

                            $translations[] = new GlossaryPhrase($language, $translationData['phrase']);
                        }

                        $items[] = new GlossaryItem($primaryPhrase, $description, ...$translations);
                    }
                }

                if (count($items) === 0) {
                    throw new \RuntimeException("No valid items found in the JSON file that match the project's primary language.");
                }

                // Заменяем все существующие элементы новыми
                $glossary->setItems(...$items);

                // Сохраняем изменения
                $this->modelManager->commit(new Transaction([$glossary]));

                // Перенаправляем на страницу редактирования глоссария
                return $response->withRedirect((new RouteUri($request))("glossary/{$glossary->id()}"));

            } catch (\RuntimeException $exception) {
                $error = $exception->getMessage();
            } catch (BulkWriteException $exception) {
                if ($exception->getCode() === 11000) {
                    $error = 'Key prefix already used';
                } else {
                    $error = 'Database error during import: ' . $exception->getMessage();
                }
            } catch (\Throwable $e) {
                $error = 'An unexpected error occurred during import: ' . $e->getMessage();
            }
        }

        // Если произошла ошибка, возвращаемся на страницу редактирования с сообщением
        $redirectUrl = (new RouteUri($request))("glossary/{$glossary->id()}");
        if (!empty($error)) {
            $redirectUrl .= '?error=' . urlencode($error);
        }
        return $response->withRedirect($redirectUrl);
    }
}