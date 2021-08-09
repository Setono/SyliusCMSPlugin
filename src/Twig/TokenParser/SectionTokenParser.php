<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\TokenParser;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class SectionTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): SectionNode
    {
        $parser = $this->parser;
        $stream = $parser->getStream();

        $name = $stream->expect(Token::NAME_TYPE)->getValue();

        $stream->expect(Token::BLOCK_END_TYPE);

        return new SectionNode($name, $token->getLine(), $this->getTag());
    }

    public function decideBlockEnd(Token $token): bool
    {
        return $token->test('endsection');
    }

    public function getTag(): string
    {
        return 'sscms_section';
    }
}
