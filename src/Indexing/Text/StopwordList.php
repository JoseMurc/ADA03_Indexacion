<?php

declare(strict_types=1);

namespace App\Indexing\Text;

/**
 * Lista de stopwords (español e ingles basico) ya normalizadas (sin acentos).
 */
final class StopwordList
{
    /** @var array<string,true> */
    private const STOPWORDS = [
        // Español
        'a' => true, 'al' => true, 'algo' => true, 'algunas' => true, 'algunos' => true,
        'ante' => true, 'antes' => true, 'como' => true, 'con' => true, 'contra' => true,
        'cual' => true, 'cuando' => true, 'de' => true, 'del' => true, 'desde' => true,
        'donde' => true, 'durante' => true, 'e' => true, 'el' => true, 'ella' => true,
        'ellas' => true, 'ellos' => true, 'en' => true, 'entre' => true, 'era' => true,
        'erais' => true, 'eran' => true, 'eras' => true, 'eres' => true, 'es' => true,
        'esa' => true, 'esas' => true, 'ese' => true, 'eso' => true, 'esos' => true,
        'esta' => true, 'estaba' => true, 'estado' => true, 'estamos' => true, 'estan' => true,
        'estar' => true, 'estas' => true, 'este' => true, 'esto' => true, 'estos' => true,
        'fue' => true, 'fueron' => true, 'ha' => true, 'habia' => true, 'han' => true,
        'hasta' => true, 'hay' => true, 'la' => true, 'las' => true, 'le' => true,
        'les' => true, 'lo' => true, 'los' => true, 'mas' => true, 'me' => true,
        'mi' => true, 'mis' => true, 'mucho' => true, 'muchos' => true, 'muy' => true,
        'nada' => true, 'ni' => true, 'no' => true, 'nos' => true, 'nosotros' => true,
        'nuestra' => true, 'nuestro' => true, 'o' => true, 'os' => true, 'otra' => true,
        'otro' => true, 'para' => true, 'pero' => true, 'poco' => true, 'por' => true,
        'porque' => true, 'que' => true, 'quien' => true, 'se' => true, 'sea' => true,
        'ser' => true, 'si' => true, 'sin' => true, 'sobre' => true, 'su' => true,
        'sus' => true, 'te' => true, 'tener' => true, 'tengo' => true, 'ti' => true,
        'tiene' => true, 'todo' => true, 'todos' => true, 'tu' => true, 'tus' => true,
        'un' => true, 'una' => true, 'uno' => true, 'unos' => true, 'y' => true,
        'ya' => true, 'yo' => true,
        // Ingles
        'about' => true, 'after' => true, 'all' => true, 'an' => true, 'and' => true,
        'any' => true, 'are' => true, 'as' => true, 'at' => true, 'be' => true,
        'been' => true, 'but' => true, 'by' => true, 'can' => true, 'did' => true,
        'do' => true, 'does' => true, 'for' => true, 'from' => true, 'had' => true,
        'has' => true, 'have' => true, 'he' => true, 'her' => true, 'him' => true,
        'his' => true, 'how' => true, 'if' => true, 'in' => true, 'into' => true,
        'is' => true, 'it' => true, 'its' => true, 'of' => true, 'on' => true,
        'or' => true, 'our' => true, 'she' => true, 'so' => true, 'that' => true,
        'the' => true, 'their' => true, 'them' => true, 'then' => true, 'there' => true,
        'these' => true, 'they' => true, 'this' => true, 'those' => true, 'to' => true,
        'was' => true, 'we' => true, 'were' => true, 'what' => true, 'when' => true,
        'which' => true, 'who' => true, 'will' => true, 'with' => true, 'you' => true,
        'your' => true,
    ];

    public function __construct(private bool $enabled = true)
    {
    }

    public function isStopword(string $token): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return isset(self::STOPWORDS[$token]);
    }
}
