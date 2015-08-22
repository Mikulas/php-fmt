<?php

namespace PhpFmt;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter;

class Printer extends PrettyPrinter\Standard
{

	protected $keepLines = FALSE;

	public function prepare(array $stmts)
	{
		return $this->reorderRootUses($stmts);
	}

	public function reorderRootUses(array $stmts)
	{
		$hasNs = FALSE;

		foreach ($stmts as $stmt) {
			if ($stmt instanceof Stmt\Namespace_) {
				$hasNs = TRUE;
			}
		}

		if (!$hasNs) {
			return $this->sortStmts($stmts);
		}

		foreach ($stmts as $ns) {
			if (!$ns instanceof Stmt\Namespace_) {
				continue;
			}

			$ns->stmts = $this->sortStmts($ns->stmts);
		}
		return $stmts;
	}

	private function sortStmts(array $stmts)
	{
		foreach ($stmts as $stmt) {
			if ($stmt instanceof Stmt\Use_ && count($stmt->uses) > 1) {
				throw new \Exception('combined use Foo, Bar; not allowed'); // TODO split this in prepare
			}
		}

		usort($stmts, function($a, $b) {
			if ($a instanceof Stmt\Use_ && !$b instanceof Stmt\Use_) {
				return -1;

			} elseif (!$a instanceof Stmt\Use_ && $b instanceof Stmt\Use_) {
				return 1;

			} elseif ($a instanceof Stmt\Use_ && $b instanceof Stmt\Use_) {
				// both Stm\Use_

				$an = implode('\\', $a->uses[0]->name->parts);
				$bn = implode('\\', $b->uses[0]->name->parts);
				return strcasecmp($an, $bn);
			}

			return $a->getLine() < $b->getLine() ? -1 : 1;
		});

		return $stmts;
	}

	public function prettyPrint(array $stmts)
	{
		$stmts = $this->prepare($stmts);
		return parent::prettyPrint($stmts) . "\n";
	}

	public function prettyPrintFile(array $stmts)
	{
		return parent::prettyPrintFile($stmts) . "\n";
	}

	/** @internal */
	public function pModifiers($modifiers) {
		return ($modifiers & Stmt\Class_::MODIFIER_FINAL ? 'final '     : '')
		. ($modifiers & Stmt\Class_::MODIFIER_ABSTRACT   ? 'abstract '  : '')
		. ($modifiers & Stmt\Class_::MODIFIER_STATIC     ? 'static '    : '')
		. ($modifiers & Stmt\Class_::MODIFIER_PUBLIC     ? 'public '    : '')
		. ($modifiers & Stmt\Class_::MODIFIER_PROTECTED  ? 'protected ' : '')
		. ($modifiers & Stmt\Class_::MODIFIER_PRIVATE    ? 'private '   : '');
	}


	/**
	 * add 2 empty lines before class,
	 * add empty line after class header,
	 */
	protected function pClassCommon(Stmt\Class_ $node, $afterClassToken) {
        return "\n\n"
	        . $this->pModifiers($node->type)
	        . 'class' . $afterClassToken
	        . (null !== $node->extends ? ' extends ' . $this->p($node->extends) : '')
	        . (!empty($node->implements) ? ' implements ' . $this->pCommaSeparated($node->implements) : '')
	        . "\n" . '{' . "\n" . $this->pStmts($node->stmts) . "\n" . '}';
    }

	/**
	 * add empty line after property declaration
	 */
    public function pStmt_Property(Stmt\Property $node) {
        return parent::pStmt_Property($node) . "\n";
    }

	/**
	 * add empty lines after method declaration
	 */
	public function pStmt_ClassMethod(Stmt\ClassMethod $node) {
		return $this->pModifiers($node->type)
		. 'function ' . ($node->byRef ? '&' : '') . $node->name
		. '(' . $this->pCommaSeparated($node->params) . ')'
		. (null !== $node->returnType ? ' : ' . $this->pType($node->returnType) : '')
		. (null !== $node->stmts
			? "\n" . '{' . $this->pMethodStmts($node->stmts) . "\n" . '}'
			: ';')
		. "\n";
	}

	public function pMethodStmts(array $nodes)
	{
		$this->keepLines = TRUE;
		$res = $this->pStmts($nodes);
		$this->keepLines = FALSE;
		return $res;
	}

	public function pScalar_LNumber(Node\Scalar\LNumber $node)
	{
		// set by KeepOriginalValueLexer
		return (string) $node->getAttributes()['originalValue'];
	}

	/**
	 * adds up to two empty lines based on the original code if $this->keepLines
	 * indent with tabs
	 *
	 * @param Node[] $nodes  Array of nodes
	 * @param bool   $indent Whether to indent the printed nodes
	 *
	 * @return string Pretty printed statements
	 */
	protected function pStmts(array $nodes, $indent = true) {
		$result = '';
		$lastLine = NULL;
		$isFirstNode = TRUE;
		$previousNode = NULL;
		foreach ($nodes as $node) {
			$result .= "\n";
			if ($this->keepLines) {
				if ($lastLine !== NULL) {
					$result .= str_repeat("\n", min(2, $node->getLine() - $lastLine - 1));
				}
			}

			$prefix = $postfix = '';
			if (!$isFirstNode && $node instanceof Stmt\ClassMethod) {
				$prefix = "\n";
			}
			if ($previousNode instanceof Stmt\ClassConst && $node instanceof Stmt\Property) {
				$prefix = "\n";
			}

			$result .= $prefix
				. $this->pComments($node->getAttribute('comments', []))
				. $this->p($node)
				. ($node instanceof Expr ? ';' : '');

			$lastLine = $node->getAttributes()['endLine'];
			$isFirstNode = FALSE;
			$previousNode = $node;
		}

		if ($indent) {
			$lines = preg_replace('~\n(?!$|' . $this->noIndentToken . ')~', "\n\t", $result);
			return preg_replace('~[ \t]+$~m', '', $lines); // trim trialing whitespace
		} else {
			return $result;
		}
	}

	public function pExpr_Array(Expr\Array_ $node) {
		$count = count($node->items);

		if ($count === 0) {
			return '[]';
		}

		$inline = $this->pCommaSeparated($node->items);
		if (strlen($inline) <= 60) {
			return "[$inline]";
		}

		$out = "[\n";
		foreach ($node->items as $item)
		{
			$out .= "\t" . $this->p($item) . ",\n";
		}
		$out .= "]";
		return $out;
	}


	public function pStmt_ClassConst(Stmt\ClassConst $node)
	{
		return parent::pStmt_ClassConst($node) . "\n";
	}


	/**
	 * Pretty prints an array of nodes and implodes the printed values with commas.
	 *
	 * @param Node[] $nodes Array of Nodes to be printed
	 *
	 * @return string Comma separated pretty printed nodes
	 */
	protected function pCommaSeparated(array $nodes) {
		return $this->pImplode($nodes, ", ");
	}

//	/**
//	 * "{$view[0][2]}"
//	 * "{$view[$foo][2]}"
//	 * "$view[$foo]"
//	 * "$view[0]"
//	 *
//	 * @param array $encapsList
//	 * @param $quote
//	 * @return string
//	 */
//	public function pEncapsList(array $encapsList, $quote) {
//		$return = '';
//		foreach ($encapsList as $element) {
//			if (is_string($element)) {
//				$return .= addcslashes($element, "\n\r\t\f\v$" . $quote . "\\");
//			} else {
//				if ($element instanceof Expr\Variable) {
//					$return .= $this->p($element);
//				} else if ($element instanceof Expr\ArrayDimFetch && !($element->var instanceof Expr\ArrayDimFetch)) {
//					$return .= $this->p($element);
//				} else {
//					$return .= '{' . $this->p($element) . '}';
//				}
//			}
//		}
//
//		return $return;
//	}

	// CONTROL BLOCKS OPENING BRACKET ON EMPTY LINE
/*
	public function pStmt_If(Stmt\If_ $node) {
		return 'if (' . $this->p($node->cond) . ")\n{"
		. $this->pStmts($node->stmts) . "\n" . '}'
		. $this->pImplode($node->elseifs)
		. (null !== $node->else ? $this->p($node->else) : '');
	}

	public function pStmt_ElseIf(Stmt\ElseIf_ $node) {
		return "\nelse if (" . $this->p($node->cond) . ")\n{"
		. $this->pStmts($node->stmts) . "\n" . '}';
	}

	public function pStmt_Else(Stmt\Else_ $node) {
		return "\nelse\n{" . $this->pStmts($node->stmts) . "\n" . '}';
	}

	public function pStmt_For(Stmt\For_ $node) {
		return 'for ('
		. $this->pCommaSeparated($node->init) . ';' . (!empty($node->cond) ? ' ' : '')
		. $this->pCommaSeparated($node->cond) . ';' . (!empty($node->loop) ? ' ' : '')
		. $this->pCommaSeparated($node->loop)
		. ")\n{" . $this->pStmts($node->stmts) . "\n" . '}';
	}

	public function pStmt_Foreach(Stmt\Foreach_ $node) {
		return 'foreach (' . $this->p($node->expr) . ' as '
		. (null !== $node->keyVar ? $this->p($node->keyVar) . ' => ' : '')
		. ($node->byRef ? '&' : '') . $this->p($node->valueVar) . ")\n{"
		. $this->pStmts($node->stmts) . "\n" . '}';
	}

	public function pStmt_While(Stmt\While_ $node) {
		return 'while (' . $this->p($node->cond) . ")\n{"
		. $this->pStmts($node->stmts) . "\n" . '}';
	}

	public function pStmt_Do(Stmt\Do_ $node) {
		return "do\n{" . $this->pStmts($node->stmts) . "\n"
		. "}\nwhile (" . $this->p($node->cond) . ');';
	}

	public function pStmt_Switch(Stmt\Switch_ $node) {
		return 'switch (' . $this->p($node->cond) . ")\n{"
		. $this->pStmts($node->cases) . "\n" . '}';
	}

	public function pStmt_TryCatch(Stmt\TryCatch $node) {
		return "try\n{" . $this->pStmts($node->stmts) . "\n" . '}'
		. $this->pImplode($node->catches)
		. ($node->finallyStmts !== null
			? " finally\n{" . $this->pStmts($node->finallyStmts) . "\n" . '}'
			: '');
	}

	public function pStmt_Catch(Stmt\Catch_ $node) {
		return ' catch (' . $this->p($node->type) . ' $' . $node->var . ")\n{"
		. $this->pStmts($node->stmts) . "\n" . '}';
	}
*/

}
