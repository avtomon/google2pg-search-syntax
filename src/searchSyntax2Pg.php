<?php

namespace avtomon;

class searchSyntax2PgException extends \Exception
{
}

class searchSyntax2Pg
{
    /**
     * Возвращает поисковую фразу в формате корректном для добавления в запрос к БД PostgreSQL для поиск совпадений
     *
     * @param string $search - поисковая фраза
     * @param string $syntaxModel - формат входных данных
     *
     * @return string
     */
    public static function getPgSyntax(string $search, string $syntaxModel = 'google', string $config = 'russian'):string
    {
        $search = trim($search);
        if (!$search || !preg_match_all('/(-?[^\s"]+)|"(.+?)"/i', $search, $matches)) {
            throw new googleSearchSyntax2PgException('Поисковая фраза не содержит слов');
        }

        $result = [];
        $matches = $matches[0];
        foreach ($matches as $index => $match) {
            switch ($syntaxModel) {
                case 'google':
                    $condition = strpos($match, ' ') !== false;
                    break;

                case 'yandex':
                    $condition = strpos($match, ' ') !== false || $match[0] === '!';
                    break;

                default:
                    throw new searchSyntax2PgException("Модель синтаксиса $syntaxModel не поддерживается");
            }

            if ($condition) {
                $result[] = self::getExactFromGoogle($match) . '::tsquery';
                continue;
            }

            if ($match == 'OR') {
                if ($index != 0 && !empty($matches[$index + 1])) {
                    $result[] = '||';
                }
                continue;
            }

            $result[] = "to_tsuqery('$config', " . str_replace('-', '!', $match) . "')";

            if (!empty($matches[$index + 1])) {
                $result[] = '&&';
            }
        }

        return implode('', $result);
    }

    /**
     * Возвращает подстроку "точного совпадения" ("слово1 слово2..." или !слово) в Posgres-формате
     *
     * @param string $str подстрака точного совпадения
     *
     * @return string
     */
    public static function getExact(string $str): string
    {
        $str = trim($str);
        if (!$str) {
            throw new searchSyntax2PgException('Точная поисковая фраза не содержит слов');
        }

        if (!preg_match_all('/[\*\s]+/i', $str, $matches)) {
            return $str;
        }

        foreach ($matches[0] as $match)
        {
            $len = strlen(str_replace(' ', '', $match)) + 1;
            $str = str_replace($match, "<$len>", $str);
        }

        return $str;
    }
}