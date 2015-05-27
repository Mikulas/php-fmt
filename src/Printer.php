<?php

use PhpParser\Node\Stmt;

class Printer extends PhpParser\PrettyPrinter\Standard
{

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

	protected function pClassCommon(Stmt\Class_ $node, $afterClassToken) {
        return "\n\n"
	        . $this->pModifiers($node->type)
	        . 'class' . $afterClassToken
	        . (null !== $node->extends ? ' extends ' . $this->p($node->extends) : '')
	        . (!empty($node->implements) ? ' implements ' . $this->pCommaSeparated($node->implements) : '')
	        . "\n" . '{' . "\n" . $this->pStmts($node->stmts) . "\n" . '}';
    }

    public function pStmt_Property(Stmt\Property $node) {
        return parent::pStmt_Property($node) . "\n";
    }

}
