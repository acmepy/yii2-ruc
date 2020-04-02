<?php

namespace acmepy\ruc;

use Yii;

/**
 * This is just an example.
 */
class Import extends \yii\base\Widget{
	
	private $url = 'https://www.set.gov.py/rest/contents/download/collaboration/sites/PARAGUAY-SET/documents/informes-periodicos/ruc/';
	private $path;

	public $model;
	public $can_upd = 100;
	public $can_por = 20000;

	private $des_oper;
	private $has_oper;
	private $cur_file;
	private $cur_proc;
	private $count;
	private $porcentaje;
	
	public function init(){
		$this->des_oper = new \DateTime('now');
		$this->setStatus(true);
		if (!isset($this->path)){
			$this->path = sys_get_temp_dir() . '/';
		}
		if (!isset($this->model)){
			$this->model = 'tax\models\Ruc';
		}
	}
	
    public function run(){
		echo '<div id="log-ruc"></div>';
		$this->cur_proc = 'Iniciando';
		$this->setStatus();
		session_write_close();
		//ini_set('memory_limit', '1G');
		set_time_limit(14400); 


		$this->porcentaje = 0;
		$this->count = 0;
		for ($i=0;$i<=9;$i++){
			$this->cur_file = $i;
			$f = 'ruc' . $i;
			$this->download($f.'.zip');
			$this->descomprimir($f.'.zip');
			$this->leer($f.'.txt');
			unlink($this->path . $f.'.txt');
			$this->porcentaje = ($i+1)*10;

		}
		$this->has_oper = new \DateTime('now');
		$this->cur_proc = 'Finalizado';
		$this->setStatus();
		return true;
        //return ($this->ini_oper->diff($this->has_oper))->format("%H:%I:%S");
    }
	
	private function download($pfile){
		$this->cur_proc = 'Descargando ' . $pfile;
		$this->porcentaje++;
		$this->setStatus();
		$ci = curl_init();
		$url = $this->url . $pfile;
		$fp = fopen($this->path. $pfile, "w");
		curl_setopt_array( $ci, array(
			CURLOPT_URL => $url,
			CURLOPT_TIMEOUT => 3600,
			CURLOPT_FILE => $fp
		));
		$contents = curl_exec($ci); // Returns '1' if successful
		curl_close($ci);
		fclose($fp);
		return $contents;
	}

	private function descomprimir($pfile){
		$this->cur_proc = 'Descomprimiendo ' . $pfile;
		$this->porcentaje++;
		$this->setStatus();
		$file = $this->path . $pfile;
		$path = pathinfo(realpath($file), PATHINFO_DIRNAME);
		$zip = new \ZipArchive;
		$res = $zip->open($file);
		if ($res === TRUE) {
			$zip->extractTo($path);
			$zip->close();
			return true;
		} else {
			return false;
		}
	}

	private function leer($pfile){
		$this->cur_proc = 'Procesando ' . $pfile;
		$this->porcentaje++;
		$this->setStatus();
		$a = fopen($this->path . $pfile, 'r');
		$i = 0;
		while ($l = fgets($a)) {
			$r = explode('|',$l);
			unset($r[count($r)-1]);
			$this->insert($r);
			if (round(($i)/$this->can_upd)==$i/$this->can_upd){
				$this->cur_proc = $this->count . ' procesados';
				if (round($i/$this->can_por)==$i/$this->can_por){
					$this->porcentaje++;
				}
				$this->setStatus();
			}
			$i++;
			$this->count++;
		}
		fclose($a);
		return true;
	}
	
	private function insert($row){
		$m = new $this->model;
		$m->ruc = $row[0];
		$m->dv = $row[2];
		$m->nombre = $row[1];
		$m->equivalencia = $row[3];
		$m->save();
	}

	private function setStatus($purgue=false){
		Yii::$app->cache->set('rucImportProgress.json', json_encode([
			'Archivos procesados' => $this->cur_file+1 . ' de 10',
			'Registros' => $this->cur_proc,
			'Porcentaje' => $this->porcentaje,
			'Tiempo' => ($this->des_oper->diff(new \DateTime('now')))->format("%H:%I:%S"),
			//'updated' => time(),
		]));
	}
	
	public static function status(){
		return Yii::$app->cache->get('rucImportProgress.json');
	}
}
