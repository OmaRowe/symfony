<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@symfony.com>
 */
class ExpressionValidator extends ConstraintValidator implements ExpressionFunctionProviderInterface
{
    private ExpressionLanguage $expressionLanguage;

    public function __construct(ExpressionLanguage $expressionLanguage = null)
    {
        if ($expressionLanguage) {
            $this->expressionLanguage = clone $expressionLanguage;
            $this->expressionLanguage->registerProvider($this);
        }
    }

    /**
     * @return void
     */
    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof Expression) {
            throw new UnexpectedTypeException($constraint, Expression::class);
        }

        $variables = $constraint->values;
        $variables['value'] = $value;
        $variables['this'] = $this->context->getObject();
        $variables['context'] = $this->context;

        if ($constraint->negate xor $this->getExpressionLanguage()->evaluate($constraint->expression, $variables)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value, self::OBJECT_TO_STRING))
                ->setCode(Expression::EXPRESSION_FAILED_ERROR)
                ->addViolation();
        }
    }

    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('is_valid', function (...$arguments) {
                return sprintf(
                    '0 === $context->getValidator()->inContext($context)->validate(%s)->getViolations()->count()',
                    implode(', ', $arguments)
                );
            }, function (array $variables, ...$arguments): bool {
                return 0 === $variables['context']->getValidator()->inContext($variables['context'])->validate(...$arguments)->getViolations()->count();
            }),
        ];
    }

    private function getExpressionLanguage(): ExpressionLanguage
    {
        if (!isset($this->expressionLanguage)) {
            $this->expressionLanguage = new ExpressionLanguage();
            $this->expressionLanguage->registerProvider($this);
        }

        return $this->expressionLanguage;
    }
}
