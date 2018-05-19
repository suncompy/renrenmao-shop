<?php
namespace Admin\Model;
class ExcelModel extends AdminModel {

  public function __construct() {
  
  /*导入phpExcel核心类 */
  require_once APP_PATH.'Common/tools/PHPExcel/PHPExcel.php'; 
  require_once APP_PATH.'Common/tools/PHPExcel/PHPExcel/Writer/Excel5.php';     // 用于其他低版本xls 
    require_once APP_PATH.'Common/tools/PHPExcel/PHPExcel/Writer/Excel2007.php'; // 用于 excel-2007 格式 
  }


//导入excel内容转换成数组 每页的内容格式相同
public function import($filePath){
  $this->__construct();
  $PHPExcel = new \PHPExcel(); 

  /**默认用excel2007读取excel，若格式不对，则用之前的版本进行读取*/ 
  $PHPReader = new \PHPExcel_Reader_Excel2007(); 
  if(!$PHPReader->canRead($filePath)){ 
    $PHPReader = new \PHPExcel_Reader_Excel5(); 
    if(!$PHPReader->canRead($filePath)){ 
    echo '没有找到上传的文件'; 
    return; 
    } 
  } 

  $PHPExcel = $PHPReader->load($filePath); 
  $num = $PHPExcel->getSheetCount();
  $erp_orders_id = array();  //声明数组
  for( $i = 0 ;$i < $num ; $i ++){
    $currentSheet = $PHPExcel->getSheet($i);  //读取excel文件中的第一个工作表
    $allColumn = $currentSheet->getHighestColumn(); //取得最大的列号
    $allRow = $currentSheet->getHighestRow(); //取得一共有多少行
    $erp_orders_id['title'][$i] = $currentSheet->getTitle();
    // 从第二行开始输出，因为excel表中第一行为列名
    for($currentRow = 2;$currentRow <= $allRow;$currentRow++){ 
    /**从第A列开始输出*/ 
      for($currentColumn= 'A';$currentColumn<= $allColumn; $currentColumn++){ 
        $val = $currentSheet->getCellByColumnAndRow(ord($currentColumn) - 65,$currentRow)->getValue();/**ord()将字符转为十进制数*/
           $erp_orders_id['arr'][$i][$currentRow][ord($currentColumn)-65] = $val; 

        /**如果输出汉字有乱码，则需将输出内容用iconv函数进行编码转换，如下将gb2312编码转为utf-8编码输出*/ 
        //echo iconv('utf-8','gb2312', $val)."\t";   
      }
    } 
  }
    return $erp_orders_id;
}

//导出Excel表格
public function export($data,$excelFileName,$sheetTitle ,$width =null){

    $this->__construct();
    /* 实例化类 */
    $objPHPExcel = new \PHPExcel(); 
    
    /* 设置输出的excel文件为2007兼容格式 */
    //$objWriter=new PHPExcel_Writer_Excel5($objPHPExcel);//非2007格式
    $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
    
    /* 设置当前的sheet */
    $objPHPExcel->setActiveSheetIndex(0);
  
    $objActSheet = $objPHPExcel->getActiveSheet();
    
  /*设置宽度*/
  if(!empty($width)){
    foreach ($width as $k => $v) {
      $objPHPExcel->getActiveSheet()->getColumnDimension($v[0])->setWidth($v[1]);
    }
  }
    /* sheet标题 */
    $objActSheet->setTitle($sheetTitle);
    
    $i = 1;
    foreach($data as $value)
    {
        /* excel文件内容 */
        $j = 'A';
        foreach($value as $value2)
        { 
//            $value2=iconv("gbk","utf-8",$value2);
            $objActSheet->setCellValue($j.$i,$value2);
            $j++;
        }
        $i++;
    }
    
  
    /* 生成到浏览器，提供下载 */ 
    ob_end_clean();  //清空缓存
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control:must-revalidate,post-check=0,pre-check=0");
    header("Content-Type:application/force-download");
    header("Content-Type:application/vnd.ms-execl");
    header("Content-Type:application/octet-stream");
    header("Content-Type:application/download");
    header('Content-Disposition:attachment;filename="'.$excelFileName.'.xlsx"');
    header("Content-Transfer-Encoding:binary"); 
    $objWriter->save('php://output');
}


}
?> 