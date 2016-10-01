<?php
/**
 * GettextExtractor
 *
 * This source file is subject to the New BSD License.
 *
 * @copyright Copyright (c) 2012 Ondřej Vodáček
 * @license New BSD License
 * @package Nette Extras
 */

/**
 * Filter to fetch gettext phrases from PHP functions
 * @author Ondřej Vodáček
 */
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\ParserFactory;

class GettextExtractor_Filters_PHPFilter extends GettextExtractor_Filters_AFilter implements GettextExtractor_Filters_IFilter, NodeVisitor {

	/** @var array */
	private $data;

	public function __construct() {
		$this->addFunction('gettext', 1);
		$this->addFunction('_', 1);
		$this->addFunction('ngettext', 1, 2);
		$this->addFunction('_n', 1, 2);
		$this->addFunction('pgettext', 2, null, 1);
		$this->addFunction('_p', 2, null, 1);
		$this->addFunction('npgettext', 2, 3, 1);
		$this->addFunction('_np', 2, 3, 1);
	}

	/**
	 * Parses given file and returns found gettext phrases
	 *
	 * @param string $file
	 * @return array
	 */
	public function extract($file) {
		$this->data = array();
		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP5);
		$stmts = $parser->parse(file_get_contents($file));;
		$traverser = new NodeTraverser;
		$traverser->addVisitor($this);
		$traverser->traverse($stmts);
		$data = $this->data;
		$this->data = null;
		return $data;
	}

	public function enterNode(Node $node) {
		$name = null;
		if (($node instanceof MethodCall || $node instanceof StaticCall) && is_string($node->name)) {
			$name = $node->name;
		} elseif ($node instanceof FuncCall && $node->name instanceof Name) {
			$parts = $node->name->parts;
			$name = array_pop($parts);
		} else {
			return;
		}
		if (!isset($this->functions[$name])) {
			return;
		}
		foreach ($this->functions[$name] as $definition) {
			$this->processFunction($definition, $node);
		}
	}

	private function processFunction(array $definition, Node $node) {
		$message = array(
			GettextExtractor_Extractor::LINE => $node->getLine()
		);
		foreach ($definition as $type => $position) {
			if (!isset($node->args[$position - 1])) {
				return;
			}
			$arg = $node->args[$position - 1]->value;
			if ($arg instanceof Node\Scalar\String_) {
				$message[$type] = $arg->value;
			} elseif ($arg instanceof Node\Expr\Array_) {
				foreach ($arg->items as $item) {
					if ($item->value instanceof Node\Scalar\String_) {
						$message[$type][] = $item->value->value;
					}
				}
				if (count($message) === 1) { // line only
					return;
				}
			} else {
				return;
			}
		}
		if (is_array($message[GettextExtractor_Extractor::SINGULAR])) {
			foreach ($message[GettextExtractor_Extractor::SINGULAR] as $value) {
				$tmp = $message;
				$tmp[GettextExtractor_Extractor::SINGULAR] = $value;
				$this->data[] = $tmp;
			}
		} else {
			$this->data[] = $message;
		}
	}

	/*** PHPParser_NodeVisitor: dont need these *******************************/

	public function afterTraverse(array $nodes) {
	}

	public function beforeTraverse(array $nodes) {
	}

	public function leaveNode(Node $node) {
	}
}
