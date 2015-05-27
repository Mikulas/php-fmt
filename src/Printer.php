<?php

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

class Printer extends PhpParser\PrettyPrinter\Standard
{

	protected $keepLines = FALSE;

	public function prepare(array $stmts)
	{
		return $this->reorderRootUses($stmts);
	}

	public function reorderRootUses(array $stmts)
	{
		/** @var Stmt\Namespace_ $ns */
		foreach ($stmts as &$ns)
		{
			foreach ($ns->stmts as $node) {
				if ($node instanceof Stmt\Use_ && count($node->uses) > 1) {
					throw new Exception('use Foo, Bar; not allowed'); // TODO split this in prepare
				}
			}

			usort($ns->stmts, function($a, $b) {
				if ($a instanceof Stmt\Use_ && $b instanceof Stmt\Use_)
				{
					$an = implode('\\', $a->uses[0]->name->parts);
					$bn = implode('\\', $b->uses[0]->name->parts);
					return strcmp($an, $bn);
				}
				return 1;
			});
		}
		return $stmts;
	}

	public function prettyPrint(array $stmts)
	{
		$stmts = $this->prepare($stmts);
		return parent::prettyPrint($stmts);
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
	 * add empty line after method declaration
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
		foreach ($nodes as $node) {
			$result .= "\n";
			if ($this->keepLines) {
				if ($lastLine !== NULL) {
					$result .= str_repeat("\n", min(2, $node->getLine() - $lastLine - 1));
				}
			}
			$result .=
				$this->pComments($node->getAttribute('comments', []))
				. $this->p($node)
				. ($node instanceof Expr ? ';' : '');

			$lastLine = $node->getAttributes()['endLine'];
		}

		if ($indent) {
			return preg_replace('~\n(?!$|' . $this->noIndentToken . ')~', "\n\t", $result);
		} else {
			return $result;
		}
	}

	// CONTROL BLOCKS OPENING BRACKET ON EMPTY LINE

	public function pStmt_If(Stmt\If_ $node) {
		return 'if (' . $this->p($node->cond) . ")\n{"
		. $this->pStmts($node->stmts) . "\n" . '}'
		. $this->pImplode($node->elseifs)
		. (null !== $node->else ? $this->p($node->else) : '');
	}

	public function pStmt_ElseIf(Stmt\ElseIf_ $node) {
		return ' elseif (' . $this->p($node->cond) . ")\n{"
		. $this->pStmts($node->stmts) . "\n" . '}';
	}

	public function pStmt_Else(Stmt\Else_ $node) {
		return " else\n{" . $this->pStmts($node->stmts) . "\n" . '}';
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

}
