<?php
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL ^ E_NOTICE);

class Char{
	private $_type = 0;
	private $_attributes = array();
	
	function __construct($type=1, $level=1){
		$this->_type = $type;
		$this->_attributes = $this->genAttributes($level);
	}
	
	private function genAttributes($level = 1){
		$fluctuate = rand(1, 5) * 0.05;
		$fluctuate_ratio = 1 + $fluctuate;
		$arr['_l'] = max(1, min($level, 100));
		$arr['_a'] = ($arr['_l']) * $fluctuate_ratio;
		$arr['_d'] = (1 + $arr['_l'] * 0.5) * $fluctuate_ratio;
		$arr['_f'] = (1 + $arr['_l'] * 0.08) * $fluctuate_ratio;
		$arr['_h'] = ($arr['_l'] * 10) * $fluctuate_ratio;
		return $arr;
	}
	
	function getAttributes(){
		return isset($this->_attributes) ? $this->_attributes : $this->genAttributes();
	}
	
	function setH($h){
		$this->_attributes['_h'] = $h;
	}
	
	static function fight($char1, $char2){
		$attrs1 = $char1->getAttributes();
		$attrs2 = $char2->getAttributes();
		while ($attrs1['_h'] > 0 && $attrs2['_h'] > 0){
			if ($attrs1['_h'] > 0){
				$attrs2['_h'] -= $attrs1['_a'] * (1 - $attrs2['_d'] / ($attrs1['_a'] + $attrs2['_d'])) * $attrs1['_f'];
				$char2->setH($attrs2['_h']); 
			}
			if ($attrs2['_h'] > 0){
				$attrs1['_h'] -= $attrs2['_a'] * (1 - $attrs1['_d'] / ($attrs2['_a'] + $attrs1['_d'])) * $attrs2['_f']; 
				$char1->setH($attrs1['_h']); 
			}
			var_dump("char1_h:{$attrs1['_h']}, char2_h:{$attrs2['_h']}");
		}
	}
}

class Map{
	private $_map;
	private $_h;
	private $_w;
	private $_terrain;
	private $_objects;
	private $_openList;
	private $_closedList; 
	
	function __construct($w=10, $h=10, $terrain='.'){
		$this->_h = $h;
		$this->_w = $w;
		$this->_terrain = $terrain;
		
		for ($y=1; $y<=$this->_h; $y++){
			for ($x=1; $x<=$this->_w; $x++){
				$this->_map[$x][$y][] = array('img' => $terrain, 'walkable'=>true);
			}
		}
	}
	
	function addLayer($layer=array(), $is_terrain=true){
		if (empty($layer)){
			return false;
		}
		foreach ($layer as $node){
			if ($is_terrain){
				$this->_map[$node['x']][$node['y']][] = $node;
			}else{
				$topTerrainNode = $this->getTopNode($this->_map[$node['x']][$node['y']]);
				if ($topTerrainNode['walkable']){
					$this->_objects[$node['x']][$node['y']][] = $node;
				}
			}
		}
	}
	
	function display($withObjects=true){
		for ($y=1; $y<=$this->_h; $y++){
			for ($x=1; $x<=$this->_w; $x++){
				if ($withObjects && isset($this->_objects[$x][$y])){
					$top_node = $this->getTopNode($this->_objects[$x][$y]);
				}else{
					$top_node = $this->getTopNode($this->_map[$x][$y]);
				}
				echo $top_node['img'].' ';
			}
			echo "\n";
		}
	}
	
	function getTopNode($nodes){
		return array_pop($nodes);
	}
	
	function getBottomNode($nodes){
		return array_shift($nodes);
	}
	
	function getPeripheralNodes($node, $endNode, $diagonal_enabled=false){
		if (isset($this->_map[$node['x']][$node['y']])){
			$peripheralNodes = array();
			for ($x=$node['x']-1; $x<=$node['x']+1; $x++){
				for ($y=$node['y']-1; $y<=$node['y']+1; $y++){
					if (!$diagonal_enabled && abs($node['x']-$x) + abs($node['y']-$y) > 1){
						continue;
					}
					if (! empty($this->_map[$x][$y])){
						if ($node['x']==$x && $node['y']==$y){
							continue;
						}
						
						$topTerrainNode = $this->getTopNode($this->_map[$x][$y]);
						if (! $topTerrainNode['walkable']){
							continue;
						}
						
						$n['H'] = abs($endNode['x'] - $x) + abs($endNode['y'] - $y);
						$n['G'] = min(1.4, abs($n['x']-$x) + abs($n['y']-$y)) + $node['G'];
						$n['F'] = $n['H'] + $n['G'];
						$n['x'] = $x;
						$n['y'] = $y;
						$n['parent'] = $node['x'].'_'.$node['y'];
						$n['img'] = 'o';
						$n['walkable'] = $topTerrainNode['walkable'];
						$peripheralNodes[] = $n;
					}
				}
			}
			return $peripheralNodes;
		}
	}
	
	function FCompare($n1, $n2){
		if($n1['F'] == $n2['F']){
			$ret = 0;
		}else{
			$ret = ($n1['F'] < $n2['F'])? -1 : 1;
		}
		return $ret;
	}

	function getLine($x, $y, $node){
		list($x1, $x2) = explode('-', $x);
		$x2 = empty($x2) ? $x1 : $x2;
		$x_min = min($x1, $x2);
		$x_max = max($x1, $x2);
		
		list($y1, $y2) = explode('-', $y);
		$y2 = empty($y2) ? $y1 : $y2;
		$y_min = min($y1, $y2);
		$y_max = max($y1, $y2);
		
		$lineNodes = array();
		for ($x=$x_min; $x<=$x_max; $x++){
			for ($y=$y_min; $y<=$y_max; $y++){
				if ($x<>$x_min && $x<>$x_max && $y<>$y_min && $y<>$y_max){
					continue;
				}
				$node['x'] = $x;
				$node['y'] = $y;
				$lineNodes[] = $node;
			}
		}
		return $lineNodes;
	}
	
	function getPath($start, $end, $pathNode=array('img'=>'o')){
		$this->_openList[$start['x'].'_'.$start['y']] = $start;
		$counter = 1;
		while (! (empty($this->_openList) || isset($this->_closedList[$end['x'].'_'.$end['y']]))){
			$counter++;
			// get lowest F
			uasort($this->_openList, array($this, 'FCompare'));
			$nodeCurr = $this->getBottomNode($this->_openList);
			$pNodes = $this->getPeripheralNodes($nodeCurr, $end);
			
			// mv current node to closedlist
			unset($this->_openList[$nodeCurr['x'].'_'.$nodeCurr['y']]);
			$this->_closedList[$nodeCurr['x'].'_'.$nodeCurr['y']] = $nodeCurr;
			
			foreach ($pNodes as $n){
				if (isset($this->_closedList[$n['x'].'_'.$n['y']]) || !$n['walkable']){
					continue;
				}
				
				if (empty($this->_openList[$n['x'].'_'.$n['y']])){
					$this->_openList[$n['x'].'_'.$n['y']] = $n;
				}else{
					if ($n['F'] < $this->_openList[$n['x'].'_'.$n['y']]['F']){
						$this->_openList[$n['x'].'_'.$n['y']]['parent'] = $n['x'].'_'.$n['y'];
					}
				}
			}
		}
		
		$pre = $this->_closedList[$end['x'].'_'.$end['y']];
		while (isset($pre['parent'])){
			$this->_closedList[$pre['parent']]['img'] = $pathNode['img'];
			$path[] = $this->_closedList[$pre['parent']];
			$pre = $this->_closedList[$pre['parent']];
		}
		var_dump("repeat: {$counter}\n");
		var_dump("openList: ".count($this->_openList)."\n");
		var_dump("closeList: ".count($this->_closedList)."\n");
		return $path;
	}
}

class Cli
{
	function aStar(){
		$map = new Map(60,7);
		// walls
		$wallNode = array('img'=>'#', 'walkable'=>false);
		$walls[] = $map->getLine('6', '1-2', $wallNode);
		$walls[] = $map->getLine('12', '2-5', $wallNode);
		$walls[] = $map->getLine('1-57', '4', $wallNode);
		$walls[] = $map->getLine('30', '4-6', $wallNode);
		$walls[] = $map->getLine('25', '6-7', $wallNode);
		foreach ($walls as $wall){
			$map->addLayer($wall);
		}
		
		// objects
		$objects['start'] = array('img'=>'S', 'walkable'=>true, 'x'=>2,'y'=>3); 
		$objects['end'] = array('img'=>'E', 'walkable'=>true, 'x'=>3,'y'=>5);
		$map->addLayer($objects, false);
		
		// path
		$path = $map->getPath($objects['start'], $objects['end'], array('img'=>'o'));
		$map->addLayer($path, false);
		
		$map->display(true);
	}
	
	function test(){
		$ico = new Char(0, 100);
		$a = new Char(1,11);
		var_dump($ico->getAttributes(), $a->getAttributes());
		Char::fight($ico, $a);
		var_dump($ico->getAttributes(), $a->getAttributes());
	}
}

$cli = new Cli();
if(empty($argv[1])){
	$reflector = new ReflectionClass(get_class($cli));
	echo "请输入方法名和参数. 可用的方法有: \n";
	foreach (get_class_methods($cli) as $k => $m){
		$parameters = $reflector->getMethod($m)->getParameters();
		$params_name = array(); 
		foreach ($parameters as $param){
			$params_name[] = '$'.$param->name;
		}
		echo "{$k}. {$m} (".join(', ', $params_name).") \n";
	}
	exit;
}

if(isset($argv[1]) && !is_callable($cli, $argv[1])){
	if (isset($argv[2])){
		for ($i = 2; $i < count($argv); $i++){
			$params[] = '\''.addslashes($argv[$i]).'\'';
		}
		$params_str = implode(',', $params);
	}
	eval('$cli->$argv[1]('.$params_str.');');
}else{
	echo "方法名错误：{$argv[1]}\n";
}