<?php
include '../Data.php';
include 'function/DbGetFnc.php';
include 'function/ParamLib.php';
include 'function/app_functions.php';
include 'function/function.php';

header('Content-Type: application/json');

$_SESSION['STAFF_ID'] = $teacher_id = $_REQUEST['staff_id'];
$_SESSION['UserSchool'] = $_REQUEST['school_id'];
$_SESSION['UserSyear'] = $_REQUEST['syear'];
$mp_id = $_SESSION['UserMP'] = $_REQUEST['mp_id'];
$_SESSION['student_id'] = $_REQUEST['student_id'];
$date = $_REQUEST['date'];

    $mp_id1=$_REQUEST['mp_id'];

    if((UserWs('PROFILE_ID')==3 || UserWs('PROFILE_ID')==4) && $date=='')
        $date=date('Y-m-d');   
    $mp_sql='SELECT MARKING_PERIOD_ID,START_DATE,END_DATE FROM marking_periods WHERE SYEAR=\''.UserSyear().'\' AND SCHOOL_ID=\''.UserSchool().'\' AND \''.date('Y-m-d',strtotime($date)).'\' BETWEEN START_DATE AND END_DATE';
    $mp_data=  DBGet(DBQuery($mp_sql));
    
    if(count($mp_data)==0)
    {
        $mp_sql='SELECT MARKING_PERIOD_ID,START_DATE,END_DATE FROM marking_periods WHERE SYEAR=\''.UserSyear().'\' AND SCHOOL_ID=\''.UserSchool().'\'';
        $mp_data=  DBGet(DBQuery($mp_sql));
    }
    $full_day=date('l',strtotime($date));
    $day=get_db_day($full_day);
    $fy_id = DBGet(DBQuery('SELECT MARKING_PERIOD_ID FROM school_years WHERE SYEAR=\''.UserSyear().'\' AND SCHOOL_ID=\''.UserSchool().'\''));
    $fy_id = $fy_id[1]['MARKING_PERIOD_ID'];
    for($i=1;$i<=count($mp_data);$i++)
    {
        $mp_ids_arr[]=$mp_data[$i]['MARKING_PERIOD_ID'];
    }
    
    $sql = 'SELECT
    s.COURSE_ID,
    s.COURSE_PERIOD_ID,
    s.MARKING_PERIOD_ID,
    s.START_DATE,
    s.END_DATE,
    UNIX_TIMESTAMP(s.START_DATE) AS START_EPOCH,
    UNIX_TIMESTAMP(s.END_DATE) AS END_EPOCH,
    CONCAT(sp.TITLE,\''.' - '.'\',cp.TITLE) AS PERIOD_PULLDOWN,
    sp.PERIOD_ID,CONCAT(sp.START_TIME,\''.' - '.'\',sp.END_TIME) AS TIME_PERIOD,
    cpv.PERIOD_ID,
    cp.MARKING_PERIOD_ID as COURSE_MARKING_PERIOD_ID,
    cp.MP,
    sp.SORT_ORDER,
    c.TITLE,
    cp.COURSE_PERIOD_ID AS PERIOD_PULLDOWN1,
    s.STUDENT_ID,
    r.TITLE AS ROOM,
    cpv.DAYS,
    SCHEDULER_LOCK

    FROM schedule s,courses c,course_periods cp,school_periods sp,course_period_var cpv,rooms r

    WHERE s.COURSE_ID = c.COURSE_ID 
    AND cp.COURSE_PERIOD_ID=cpv.COURSE_PERIOD_ID
    AND r.ROOM_ID=cpv.ROOM_ID
    AND s.COURSE_ID = cp.COURSE_ID
    AND s.COURSE_PERIOD_ID = cp.COURSE_PERIOD_ID
    AND s.SCHOOL_ID = sp.SCHOOL_ID 
    AND s.SYEAR = c.SYEAR 
    AND sp.PERIOD_ID = cpv.PERIOD_ID
    AND (cp.MARKING_PERIOD_ID IN ('.  implode(',',$mp_ids_arr).') OR (cp.MARKING_PERIOD_ID IS NULL AND cp.BEGIN_DATE<=\''.date('Y-m-d',strtotime($date)).'\' AND cp.END_DATE>=\''.date('Y-m-d',strtotime($date)).'\'))
    AND POSITION(\''.$day.'\' IN cpv.days)>0
    AND s.STUDENT_ID=\''. $_REQUEST['student_id'].'\'
    AND s.SYEAR=\''.UserSyear().'\' 
    AND s.SCHOOL_ID = \''.UserSchool().'\' 
    AND (cpv.COURSE_PERIOD_DATE=\''.date('Y-m-d',strtotime($date)).'\' OR cpv.COURSE_PERIOD_DATE IS NULL)
    AND (\''.date('Y-m-d',strtotime($date)).'\' BETWEEN cp.BEGIN_DATE AND cp.END_DATE) 
    AND (\''.date('Y-m-d',strtotime($date)).'\' BETWEEN s.START_DATE AND s.END_DATE OR (s.END_DATE IS NULL AND s.START_DATE<=\''.date('Y-m-d',strtotime($date)).'\')) 

    AND (s.MARKING_PERIOD_ID IN ('.GetAllMP(GetMPTable(GetMP($mp_id1,'TABLE',UserSyear(),UserSchool())),$mp_id1).') OR s.MARKING_PERIOD_ID IS NULL)

    ORDER BY sp.SORT_ORDER,s.MARKING_PERIOD_ID';

    $QI = DBQuery($sql);
    $schedule_RET = DBGet($QI,array('TIME_PERIOD'=>'_makeTimePeriod','TITLE'=>'_makeTitle','PERIOD_PULLDOWN1'=>'_makePeriodSelect')); //,'COURSE_MARKING_PERIOD_ID'=>'_makeMPSelect'
    $days_RET = DBGet(DBQuery("SELECT DISTINCT DAYS FROM course_period_var"));
    $schedule_data = array();
    if(count($schedule_RET)>0)
    {
        foreach($schedule_RET as $c=>$sch_data)
        {
            $schedule_RET[$c]['DAYS']=_makeDays($sch_data['DAYS'],'');
            $schedule_RET[$c]['TERM']=GetMP($sch_data['MARKING_PERIOD_ID'],'TITLE',$_REQUEST['syear'],$_REQUEST['school_id']);
            $schedule_data[]= $schedule_RET[$c];
        }
        
        $success = 1;
        $msg = '';
    }
    else 
    {
        $success = 0;
        $msg = 'No Courses were found.';
    }
$data = array('schedule_data'=>$schedule_data,'success'=> $success,'msg'=>$msg);

//==============================================Function start============================================
        
function _makeTitle($value,$column='')
{	
    global $_openSIS,$THIS_RET;
    return $value;
}

function _makeLock($value,$column)
{
                  global $THIS_RET;
	if($value=='Y')
		$img = 'locked';
	else
		$img = 'unlocked';

	return '<IMG SRC=assets/'.$img.'.gif '.(AllowEdit()?'onclick="if(this.src.indexOf(\'assets/locked.gif\')!=-1) {this.src=\'assets/unlocked.gif\'; document.getElementById(\'lock'.$THIS_RET['COURSE_PERIOD_ID'].'-'.$THIS_RET['START_DATE'].'\').value=\'\';} else {this.src=\'assets/locked.gif\'; document.getElementById(\'lock'.$THIS_RET['COURSE_PERIOD_ID'].'-'.$THIS_RET['START_DATE'].'\').value=\'Y\';}"':'').'><INPUT type=hidden name=schedule['.$THIS_RET['COURSE_PERIOD_ID'].']['.$THIS_RET['START_DATE'].'][SCHEDULER_LOCK] id=lock'.$THIS_RET['COURSE_PERIOD_ID'].'-'.$THIS_RET['START_DATE'].' value='.$value.'>';
}

function _makePeriodSelect($course_period_id,$column='')
{
                  global $_openSIS,$THIS_RET,$fy_id;
	$sql = 'SELECT cp.COURSE_PERIOD_ID,cp.PARENT_ID,cp.TITLE,cp.MARKING_PERIOD_ID,COALESCE(cp.TOTAL_SEATS-cp.FILLED_SEATS,0) AS AVAILABLE_SEATS,sp.TITLE AS PERIOD_TITLE  FROM course_periods cp,school_periods sp,course_period_var cpv WHERE sp.PERIOD_ID=cpv.PERIOD_ID AND cp.COURSE_PERIOD_ID=cpv.COURSE_PERIOD_ID AND cp.COURSE_ID=\''.$THIS_RET[COURSE_ID].'\' ORDER BY sp.SORT_ORDER';
	$QI = DBQuery($sql);
	$orders_RET = DBGet($QI);

	foreach($orders_RET as $value)
	{
		if($value['COURSE_PERIOD_ID']!=$value['PARENT_ID'])
		{
			$parent = DBGet(DBQuery('SELECT SHORT_NAME FROM course_periods WHERE COURSE_PERIOD_ID=\''.$value['PARENT_ID'].'\''));
			$parent = $parent[1]['SHORT_NAME'];
		}
		$periods = $value['PERIOD_TITLE'].' - '.$value['TITLE']; // . (($value['MARKING_PERIOD_ID']!=$fy_id && $value['COURSE_PERIOD_ID']!=$course_period_id)?' ('.GetMP($value['MARKING_PERIOD_ID'],'',$_REQUEST['syear'],$_REQUEST['school_id']).')':'').($value['COURSE_PERIOD_ID']!=$course_period_id?' ('.$value['AVAILABLE_SEATS'].' seats)':'').(($value['COURSE_PERIOD_ID']!=$course_period_id && isset($parent))?' -> '.$parent:'')
	}

	return $periods;
//	return SelectInput_Disonclick($course_period_id,"schedule[$THIS_RET[COURSE_PERIOD_ID]][$THIS_RET[START_DATE]][COURSE_PERIOD_ID]",'',$periods,false);
}

function _makeMPSelect($mp_id,$name='')
{
    global $THIS_RET;
    if($mp_id!='')
    return GetMP($mp_id);
    else
    {
       $check_custom=DBGet(DBQuery('SELECT BEGIN_DATE,END_DATE FROM course_periods WHERE COURSE_PERIOD_ID='.$THIS_RET['COURSE_PERIOD_ID'].' AND BEGIN_DATE IS NOT NULL AND END_DATE IS NOT NULL AND BEGIN_DATE!=\'0000-00-00\' AND END_DATE!=\'0000-00-00\' '));
       if(count($check_custom)>0)
       {
           return '<div style="white-space: nowrap;">'.ProperDateAY($check_custom[1]['BEGIN_DATE']).' to '.ProperDateAY($check_custom[1]['END_DATE']).'</div>';
       }
    }
//                 
}

function _makeDate($value,$column)//not used
{
    global $THIS_RET;

	if($column=='START_DATE')
		$allow_na = false;
	else
		$allow_na = true;

	return DateInput($value,"schedule[$THIS_RET[COURSE_PERIOD_ID]][$THIS_RET[START_DATE]][$column]",'',true,$allow_na);

}

function _str_split($str)
{
	$ret = array();
	$len = strlen($str);
	for($i=0;$i<$len;$i++)
		$ret [] = substr($str,$i,1);
	return $ret;
}

function CreateSelect($val, $name, $link='', $mpid)
	{
	 	
		
		if($link!='')
                {
		$html .= "<select title='Marking periods' name=".$name." id=".$name." onChange=\"window.location='".$link."' + this.options[this.selectedIndex].value;\">";
                }
                else
		$html .= "<select name=".$name." id=".$name." >";
		
				foreach($val as $key=>$value)
				{
					
					
					if(!isset($mpid) && (UserMP() == $value[strtoupper($name)]))
						$html .= "<option selected value=".UserMP().">".$value['TITLE']."</option>";
					else
					{
						if($value[strtoupper($name)]==$_REQUEST[$name])
							$html .= "<option selected value=".$value[strtoupper($name)].">".$value['TITLE']."</option>";
						else
							$html .= "<option value=".$value[strtoupper($name)].">".$value['TITLE']."</option>";
					}
					
				}



		$html .= "</select>";
		return $html;
	}
        
        function create_view_mode($link)
        {
            if($link!='')
                $html .= "<select title='View mode' name='view_mode' id='view_mode' onChange=\"window.location='".$link."' + this.options[this.selectedIndex].value;\">";
            else
                $html .= "<select name='view_mode' id='view_mode'>";
            
            $html .= '<option value="day_view" '.($_REQUEST['view_mode']=='day_view'? 'selected' : '').' >Day</option>';
            $html .= '<option value="week_view" '.($_REQUEST['view_mode']=='week_view'? 'selected' : '').'>Week</option>';
            $html .= '<option value="month_view" '.($_REQUEST['view_mode']=='month_view'? 'selected' : '').'>Month</option>';
            $html .= "</select>";
            return $html;
        }
        
        function get_db_day($day)
        {
            switch ($day)
            {
                case 'Sunday':
                    $return='U';
                    break;
                case 'Monday':
                    $return='M';
                    break;
                case 'Tuesday':
                    $return='T';
                    break;
                case 'Wednesday':
                    $return='W';
                    break;
                case 'Thursday':
                    $return='H';
                    break;
                case 'Friday':
                    $return='F';
                    break;
                case 'Saturday':
                    $return='S';
                    break;
            }
            return $return;
        }
        
function  weekDate($date)
{
    return date('l',strtotime($date));
}

function _makeWeeks($start,$end,$link)
{
    $one_day=60*60*24;
    $start_time=strtotime($start);
    $end_time=strtotime($end);
    if(!$_REQUEST['week_range'])
    {
            $start_time_cur=strtotime(date('Y-m-d'));
            while (date('N',$start_time_cur)!=1)
            {
                    $start_time_cur=$start_time_cur-$one_day;
            }
        $_REQUEST['week_range']=date('Y-m-d',$start_time_cur);
    }


    
    $prev=date('Y-m-d',strtotime($_REQUEST['week_range'])-$one_day*7);
    $next=date('Y-m-d',strtotime($_REQUEST['week_range'])+$one_day*7);
    $upper=date('Y-m-d',strtotime($_REQUEST['week_range'])+$one_day*6);
    if($link!=''){
      $html .= "<strong><a href='javascript:void(0);' title=Previous onClick=\"window.location='".$link.$prev."';\" style=\"font-size:12px;\">&lt;&lt; Prev</a> &nbsp; &nbsp; <span style=\"font-size:12px;\">".par_rep('/\//', ' ',properDate($_REQUEST[week_range]),1)."</span>&nbsp; - &nbsp;<span style=\"font-size:12px;\">".par_rep('/\//', ' ',properDate($upper),1)."</span> &nbsp; &nbsp; <a href='javascript:void(0);' title=Next onClick=\"window.location='".$link.$next."';\" style=\"font-size:12px;\">Next &gt;&gt;</a></strong>";
    }
    
    return $html;
}

function _makeMonths($link)
{
    $one_day=60*60*24;
    if(!$_REQUEST['month'])
    {
        $_REQUEST['month']=date(strtotime(date('Y-m-d')));
    }
    $prev=$_REQUEST['month']-$one_day*30;
    $next=$_REQUEST['month']+$one_day*30;
    if($link!=''){
      $html .= "<strong><a href='javascript:void(0);' title=Previous onClick=\"window.location='".$link.$prev."';\" style=\"font-size:12px;\">&lt;&lt; Prev</a> &nbsp; &nbsp; <span style=\"font-size:12px;\">".date('F', $_REQUEST['month'])."&nbsp;".date('Y', $_REQUEST['month'])."</span> &nbsp; &nbsp; <a href='javascript:void(0);' title=Next onClick=\"window.location='".$link.$next."';\" style=\"font-size:12px;\">Next &gt;&gt;</a></strong>";
    }
    
    return $html;
}
function _makeTimePeriod($value)
{
    $time=explode(' - ',$value);
    $time=date("g:i A", strtotime($time[0])).' - '.date("g:i A", strtotime($time[1]));
    return $time;
}
function _makeDays($value,$column)
{
    $days = array();
    $i=0;
    foreach(array('U','M','T','W','H','F','S') as $day)
    {
        if($day=='U')
            $days[$i]['day']= 1;
        elseif($day=='M')
            $days[$i]['day']= 2;
        elseif($day=='T')
            $days[$i]['day']= 3;
        elseif($day=='W')
            $days[$i]['day']= 4;
        elseif($day=='H')
            $days[$i]['day']= 5;
        elseif($day=='F')
            $days[$i]['day']= 6;
        elseif($day=='S')
            $days[$i]['day']= 7;
        if(strpos($value,$day)!==false)
        {
            $days[$i]['status']= 1;
        }
        else 
        {
            $days[$i]['status']= 0;
        }
        $i++;
//		if(strpos($value,$day)!==false)
//			$return .= $day;
//		else
//			$return .= '-';
    }
//	return '<div style="white-space: nowrap">'.$return.'</div>';
        return $days;
}
echo json_encode($data);
?>
