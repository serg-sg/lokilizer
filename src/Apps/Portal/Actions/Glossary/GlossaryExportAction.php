<?php

namespace XAKEPEHOK\Lokilizer\Apps\Portal\Actions\Glossary;

use League\Plates\Engine;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;
use XAKEPEHOK\Lokilizer\Apps\Portal\Components\RenderAction;
use XAKEPEHOK\Lokilizer\Apps\Portal\Components\RouteUri;
use XAKEPEHOK\Lokilizer\Components\Current;
use XAKEPEHOK\Lokilizer\Models\Glossary\Db\Storage\GlossaryRepo;
use XAKEPEHOK\Lokilizer\Models\Glossary\SpecialGlossary; // Добавим для проверки типа
use XAKEPEHOK\Lokilizer\Models\Project\Components\Role\Permission;

class GlossaryExportAction extends RenderAction
{
    public function __construct(
        private GlossaryRepo $glossaryRepo,
        Engine $renderer
    )
    {
        parent::__construct($renderer);
    }

    public function __invoke(Request $request, Response $response): Response
    {
        try {
            // Проверяем права
            Current::guard(Permission::MANAGE_GLOSSARY);

            // Получаем ID глоссария из URL
            $glossaryId = $request->getAttribute('id');
            $glossary = $this->glossaryRepo->findById($glossaryId);

            if (!$glossary) {
                throw new \RuntimeException("Glossary with ID {$glossaryId} not found.");
            }

            // Подготавливаем данные для экспорта
            $exportData = [
                'summary' => $glossary->getSummary(),
                'items' => [],
            ];

            foreach ($glossary->getItems() as $item) {
                $translations = [];

                // getTranslations() возвращает массив GlossaryPhrase
                // GlossaryPhrase имеет публичные свойства $language (LanguageAlpha2) и $phrase (string)
                // Однако, из-за десериализации он может быть ассоциативным массивом
                $itemTranslations = $item->getTranslations();

                // Проходим по массиву, независимо от того, индексированный он или ассоциативный
                foreach ($itemTranslations as $key => $value) {
                    // Если значение - это GlossaryPhrase, используем его
                    if (is_object($value) && $value instanceof \XAKEPEHOK\Lokilizer\Models\Glossary\GlossaryPhrase) {
                        $translationPhrase = $value;
                    } elseif (is_object($value) && property_exists($value, 'language') && property_exists($value, 'phrase')) {
                        // Если это объект с нужными свойствами (например, stdClass после json_decode)
                        $translationPhrase = $value;
                    } else {
                        // Пропускаем некорректные элементы
                        continue;
                    }

                    // Проверим, что свойства существуют и имеют правильный тип
                    if (!isset($translationPhrase->language) || !isset($translationPhrase->phrase)) {
                        continue; // Пропускаем элемент без нужных свойств
                    }
                    if (!is_object($translationPhrase->language) || !property_exists($translationPhrase->language, 'value')) {
                        continue; // Язык не является объектом LanguageAlpha2 или не имеет value
                    }

                    // Формируем объект с language и phrase для каждого перевода
                    $translations[] = [
                        'language' => $translationPhrase->language->value,
                        'phrase' => $translationPhrase->phrase,
                    ];
                }

                // Формируем объект primary с language и phrase
                $primaryData = [
                    'language' => $item->primary->language->value, // Получаем код языка
                    'phrase' => $item->primary->phrase,          // Получаем фразу
                ];

                $exportData['items'][] = [
                    'primary' => $primaryData, // Теперь primary - это объект
                    'description' => $item->description,
                    'translations' => $translations, // translations - массив объектов
                ];
            }

            // --- Новый формат имени файла ---
            $projectId = Current::getProject()->id()->get(); // Получаем ID проекта

            // Определяем keyPrefix
            $keyPrefix = 'primary'; // По умолчанию для PrimaryGlossary
            if ($glossary instanceof SpecialGlossary) {
                $keyPrefix = $glossary->getKeyPrefix();
                if (!$keyPrefix || $keyPrefix === '*') { // Если не установлен или звёздочка
                    $keyPrefix = $glossary->id()->get(); // Используем ID глоссария
                }
            }

            // Формируем имя файла
            $filename = 'glossary_' . $projectId . '_' . $keyPrefix . '_' . date('Y-m-d_H-i-s') . '.json';
            // --- /Конец нового формата ---


            // Подготовим JSON-строку
            $jsonString = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($jsonString === false) {
                throw new \RuntimeException("Failed to encode JSON: " . json_last_error_msg());
            }

            // Устанавливаем заголовки для скачивания
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Content-Disposition', 'attachment; filename="' . urlencode($filename) . '"')
                ->withHeader('Content-Length', strlen($jsonString))
                ->write($jsonString);

        } catch (\Throwable $e) {
            // Логируем ошибку (если есть логгер)
            error_log("Glossary Export Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

            // Возвращаем простой ответ с текстом ошибки, чтобы браузер не пытался скачать файл
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/plain')
                ->write("Error exporting glossary: " . $e->getMessage());
        }
    }
}