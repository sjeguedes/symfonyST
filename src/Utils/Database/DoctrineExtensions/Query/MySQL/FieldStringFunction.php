<?php

declare(strict_types = 1);

namespace App\Utils\Database\DoctrineExtensions\Query\MySQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * Class FieldStringFunction.
 *
 * Allow use of MySQL string FIELD() function in DQL query.
 *
 * @author Jeremy Hicks <jeremy.hicks@gmail.com>
 *
 * @link https://github.com/beberlei/DoctrineExtensions/blob/master/src/Query/Mysql/Field.php
 *
 * @see https://www.w3resource.com/mysql/string-functions/mysql-field-function.php
 * @see https://stackoverflow.com/questions/5957330/doctrine-2-mysql-field-function-in-order-by
 * @see https://symfony.com/doc/current/doctrine/custom_dql_functions.html
 * @see https://www.doctrine-project.org/projects/doctrine-orm/en/current/cookbook/dql-user-defined-functions.html
 * @see FIELD() can not be uses directly in query builder orderBy() method due to Doctrine ORM parser in
 * Doctrine\ORM\Query\Parser OrderByItem() method:
 * https://stackoverflow.com/questions/24765826/using-dql-functions-inside-doctrine-2-order-by
 */
class FieldStringFunction extends FunctionNode
{
    /**
     * @var Node
     */
    private $field;

    /**
     * @var array
     */
    private $values;


    /**
     * FieldStringFunction constructor.
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->values = [];
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Doctrine\ORM\Query\QueryException
     */
    public function parse(Parser $parser) : void
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        // Do the field.
        $this->field = $parser->ArithmeticPrimary();
        // Add the strings to the values array.
        // FIELD() must be used with at least 1 string value after declared field name.
        $lexer = $parser->getLexer();
        while (count($this->values) < 1 ||
            $lexer->lookahead['type'] != Lexer::T_CLOSE_PARENTHESIS) {
            $parser->match(Lexer::T_COMMA);
            $this->values[] = $parser->ArithmeticPrimary();
        }
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Doctrine\ORM\Query\AST\ASTException
     */
    public function getSql(SqlWalker $sqlWalker) : string
    {
        $query = 'FIELD(';
        $query .= $this->field->dispatch($sqlWalker);
        $query .= ', ';
        for ($i = 0; $i < count($this->values); $i++) {
            if ($i > 0) {
                $query .= ', ';
            }
            $query .= $this->values[$i]->dispatch($sqlWalker);
        }
        $query .= ')';
        return $query;
    }
}
