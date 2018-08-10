<html> 
    <head>
        <meta charset="UTF-8">
        <title>Claim Monitoring</title>
    </head>
    <body>

<?php
session_start();

ini_set ("display_errors", "1");
require_once "lib/TurboApiClient.php";
include "include/db_config_ora.php";
$OraDB = oci_connect("cpi", "cpi12345!", "10.18.0.218/PCIC");
//$OraDB = oci_connect($_SESSION['myusername1'], $_SESSION['mypassword1'],$OraServer);

$select1 = oci_parse($OraDB,"SELECT TRIM(TO_CHAR(SYSDATE,'Month'))MONTH
                                   ,TRIM(TO_CHAR(SYSDATE, 'MM'))MM
                                   ,TRIM(TO_CHAR(SYSDATE, 'YYYY'))YY
                                   ,TRIM(TO_CHAR(SYSDATE,'DD-MON-YYYY'))PARAM
                               FROM DUAL");
oci_execute($select1);
$row = oci_fetch_assoc($select1);
$day_mm = $row['MM'];
$param = $row["PARAM"];
$day_month = $row["MONTH"];
$day_yy = $row['YY'];
$todays  = date("Y/m/d");

$message1 ="";
$message2 ="";

$message1 .= "Hello Branch Head,";
$message1 .= "<br><br>Good day!";
$message1 .= "<br><br>This is the summary for *Non-moving Claims as of today. For Claims that are already paid but not yet closed, kindly review the list provided (PAID CLAIMS STILL OPEN).<br><br>";
$message2 .= "<br>*Claims that are not updated for seven working days.<br>";
$message2 .= "<br><b>THIS IS A SYSTEM GENERATED EMAIL. PLEASE DO NOT REPLY TO THIS.</b><br>";
$message2 .= "<br>Thank you.";

            $getemailcc = $dbJob->prepare("SELECT email_add FROM auto_openemail_list
                                            WHERE active_tag = 'Y'
                                              AND email_add in ('gerald.viray@axa.com.ph','rose.banaag@axa.com.ph')");
            
            $email_addresscc = array(); 
            $getemailcc->execute();
            foreach($getemailcc as $listcc){
                    $email_addresscc[] = $listcc['email_add'];  
            }

            $cc = implode(', ',$email_addresscc). "\r\n";


    //Branches for sending
    $BRSelect = $dbJob->prepare("select distinct RTRIM(LTRIM(branch))branch
                                   from user_issuance
                                  where email like '%axa%'");

    $list = array();
    $i = 0;
    $aging = 7;
    // TO
    $BRSelect->execute();
    foreach($BRSelect as $row){
        $list[$i] = $row[0];
        $i++;
    }
 
    foreach ($list as $branch) {
        $branch = "$branch";
        //select designated emails
        $email_add = "";
        $select3 = $dbJob->prepare("select distinct branch,email 
                                      from user_issuance
                                     where to_tag = 'Y' 
                                       and branch = '$branch'
                                       and email like '%axa%'");
        $select3->execute();
        foreach($select3 as $row){
            $email_add = $row['email'];
        }

        //Email Message
        $result = oci_parse($OraDB,"SELECT A.BRANCH,GET_LINE_NAME(A.LINE_CD)LINE
                                          ,DECODE(B.NON_MOVING,NULL,0,B.NON_MOVING)NON_MOVING 
                                          ,A.OPEN_CLAIMS
                                          ,ROUND((DECODE(B.NON_MOVING,NULL,0,B.NON_MOVING)/A.OPEN_CLAIMS)*100)PERCENTAGE
                                          ,DECODE(PAID_STILL_OPEN,NULL,0,PAID_STILL_OPEN)PAID_STILL_OPEN
                                     FROM
                                   (--OPEN CLAIMS
                                   SELECT get_iss_name(b.grp_iss_cd)branch,a.line_cd
                                        ,count(a.claim_id)OPEN_CLAIMS
                                     FROM GICL_CLAIMS a, GIIS_USER_GRP_HDR b
                                         ,GIIS_USERS c, giis_issource d
                                    WHERE CLM_STAT_CD NOT IN('CD','CC','WD','DN')
                                      AND a.in_hou_adj = c.user_id
                                      AND b.user_grp = c.user_grp
                                      AND b.grp_iss_cd = d.iss_Cd
                                      AND b.grp_iss_cd = '$branch'
                                      AND b.grp_iss_cd NOT IN ('MN')   
                                      AND TRUNC(A.ENTRY_DATE) <= '$param'  
                                    GROUP BY get_iss_name(b.grp_iss_cd),a.line_cd
                                   )A,-- NON MOVING CLAIMS
                                   (SELECT get_iss_name(grp_iss_cd)branch,a.line_cd
                                          ,count(a.claim_id)NON_MOVING
                                      FROM GICL_CLAIMS a, giis_users b
                                          ,GIIS_USER_GRP_HDR c 
                                     WHERE a.in_hou_adj =b.user_id 
                                       AND b.user_grp = c.user_grp
                                       AND b.active_flag = 'Y'
                                       AND c.grp_iss_cd NOT IN ('MN')   
                                       AND c.grp_iss_cd = '$branch'  
                                       AND a.clm_stat_cd NOT IN('CD','CC','WD','DN')
                                       AND calculate_business_days(A.LAST_UPDATE,SYSDATE) >= $aging
                                     GROUP BY a.line_cd, grp_iss_cd
                                   )B,
                                   (-- PAID BUT STILL OPEN
                                        SELECT COUNT(CLAIM_ID)PAID_STILL_OPEN ,LINE_CD, get_iss_name(grp_iss_cd)BRANCH
                                        FROM
                                        (
                                        SELECT a.claim_id,a.line_cd,get_claim_number (a.claim_id) claim, to_char(loss_date,'MM-DD-YYYY')loss_date, assured_name
                                              ,TRIM( TO_CHAR(SUM (disbursement_amt),'999,999,999.99')) payt_amt, get_ref_no (MAX (c.tran_id)) reference_payt
                                              ,MAX (tran_date) tran_date, DECODE (MAX (e.check_release_date), NULL, 'N', 'Y') released_check, to_char(MAX (check_date),'MM-DD-YYYY')CHECK_DATE
                                              ,calculate_business_days (MAX (check_date), SYSDATE) aging, replenished_tag, in_hou_adj, clm_stat_desc
                                              ,TRIM( TO_CHAR(SUM(NVL(LOSS_RESERVE,0)*j.CONVERT_RATE),'999,999,999.99'))LOSS_RESERVE,h.grp_iss_cd
                                        FROM gicl_claims a, giac_direct_claim_payts b, giac_acctrans c, giac_chk_disbursement d
                                            ,giac_chk_release_info e,giac_disb_vouchers f, giis_users g
                                            ,giis_user_grp_hdr h, giis_clm_stat i, GICL_CLM_RES_HIST j 
                                        WHERE a.claim_id = b.claim_id AND b.gacc_tran_id = c.tran_id 
                                          AND c.tran_flag IN ('C', 'P') 
                                          AND a.claim_id = j.claim_id(+) 
                                          AND b.gacc_tran_id = d.gacc_tran_id(+) 
                                          AND b.gacc_tran_id = e.gacc_tran_id(+) 
                                          AND b.gacc_tran_id = f.gacc_tran_id(+) 
                                          AND a.in_hou_adj = g.user_id 
                                          AND g.user_grp = h.user_grp 
                                          AND h.grp_iss_cd = '$branch'
                                          AND h.grp_iss_cd NOT IN ('MN')   
                                          AND a.clm_stat_cd = i.clm_stat_cd 
                                          AND a.clm_stat_cd NOT IN ('CD', 'CC', 'WD', 'DN') 
                                        GROUP BY get_claim_number (a.claim_id), loss_date
                                                ,assured_name, replenished_tag
                                                ,in_hou_adj,clm_stat_desc,a.claim_id ,a.line_cd,grp_iss_cd
                                          HAVING calculate_business_days (MAX (check_date), SYSDATE) >= $aging
                                        )GROUP BY LINE_CD, grp_iss_cd
                                     )C
                                   WHERE A.BRANCH = B.BRANCH(+)
                                     AND A.LINE_CD = B.LINE_CD(+)
                                     AND A.BRANCH = C.BRANCH(+)
                                     AND A.LINE_CD = C.LINE_CD(+)
                                     ORDER BY BRANCH,LINE");

        $message = '';
        $message .= "<table border=1 cellspacing=0 >
                    <tr><td colspan=6 align=center height=30><b>Non-Moving Claims</b></td></tr>
                    <tr height=30>
                        <th width=180>BRANCH</th>
                        <th width=250>LINE</th>
                        <th width=120>NON MOVING CLAIMS</th>
                        <th width=120>OPEN CLAIMS</th>
                        <th width=120>PERCENTAGE</th>
                        <th width=120>PAID CLAIMS STILL OPEN</th>
                        </tr>";
        
        oci_execute($result);
        while ($row = oci_fetch_assoc($result)) {
            $LINE        = $row['LINE'];
            $CLBRANCH    = $row['BRANCH'];
            $NON_MOVING  = $row['NON_MOVING'];
            $OPEN_CLAIMS = $row['OPEN_CLAIMS'];
            $PERCENT     = $row['PERCENTAGE'];
            $PAID        = $row['PAID_STILL_OPEN'];

            $message .= "<tr><td>$CLBRANCH</td>
                            <td>$LINE</td>
                            <td align=right>$NON_MOVING</td>
                            <td align=right>$OPEN_CLAIMS</td>
                            <td align=right>$PERCENT %</td>
                            <td align=right>$PAID</td></tr>";
        }

        $message .= "<tr><td align=center colspan=2><b>GRAND TOTAL</b></td>";
        $Totalresult = oci_parse($OraDB,"SELECT SUM(NON_MOVING)NON_MOVING, SUM(OPEN_CLAIMS)OPEN_CLAIMS
                                                ,ROUND((SUM(NON_MOVING)/SUM(OPEN_CLAIMS))*100)PERCENTAGE
                                                ,SUM(PAID_STILL_OPEN)PAID_STILL_OPEN
                                   FROM(             
                                   SELECT A.BRANCH,DECODE(B.NON_MOVING,NULL,0,B.NON_MOVING)NON_MOVING 
                                          ,A.OPEN_CLAIMS
                                          ,ROUND((DECODE(B.NON_MOVING,NULL,0,B.NON_MOVING)/A.OPEN_CLAIMS)*100)PERCENTAGE
                                          ,DECODE(PAID_STILL_OPEN,NULL,0,PAID_STILL_OPEN)PAID_STILL_OPEN
                                     FROM
                                   (--OPEN CLAIMS
                                   SELECT get_iss_name(b.grp_iss_cd)branch,a.line_cd
                                        ,count(a.claim_id)OPEN_CLAIMS
                                     FROM GICL_CLAIMS a, GIIS_USER_GRP_HDR b
                                         ,GIIS_USERS c, giis_issource d
                                    WHERE CLM_STAT_CD NOT IN('CD','CC','WD','DN')
                                      AND a.in_hou_adj = c.user_id
                                      AND b.user_grp = c.user_grp
                                      AND b.grp_iss_cd = d.iss_Cd
                                      AND b.grp_iss_cd = '$branch'
                                      AND b.grp_iss_cd NOT IN ('MN')    
                                      AND TRUNC(A.ENTRY_DATE) <= '$param'
                                    GROUP BY get_iss_name(b.grp_iss_cd),a.line_cd
                                   )A,-- NON MOVING CLAIMS
                                   (SELECT get_iss_name(grp_iss_cd)branch,a.line_cd
                                          ,count(a.claim_id)NON_MOVING
                                      FROM GICL_CLAIMS a, giis_users b
                                          ,GIIS_USER_GRP_HDR c 
                                     WHERE a.in_hou_adj =b.user_id 
                                       AND b.user_grp = c.user_grp
                                       AND b.active_flag = 'Y'
                                       AND c.grp_iss_cd = '$branch'  
                                       AND C.grp_iss_cd NOT IN ('MN')
                                       AND a.clm_stat_cd NOT IN('CD','CC','WD','DN')
                                       AND calculate_business_days(A.LAST_UPDATE,SYSDATE) >= $aging
                                     GROUP BY a.line_cd, grp_iss_cd
                                   )B,
                                   (-- PAID BUT STILL OPEN
                                   SELECT COUNT(CLAIM_ID)PAID_STILL_OPEN ,LINE_CD, get_iss_name(grp_iss_cd)BRANCH
                                        FROM
                                        (
                                        SELECT a.claim_id,a.line_cd,get_claim_number (a.claim_id) claim, to_char(loss_date,'MM-DD-YYYY')loss_date, assured_name
                                              ,TRIM( TO_CHAR(SUM (disbursement_amt),'999,999,999.99')) payt_amt, get_ref_no (MAX (c.tran_id)) reference_payt
                                              ,MAX (tran_date) tran_date, DECODE (MAX (e.check_release_date), NULL, 'N', 'Y') released_check, to_char(MAX (check_date),'MM-DD-YYYY')CHECK_DATE
                                              ,calculate_business_days (MAX (check_date), SYSDATE) aging, replenished_tag, in_hou_adj, clm_stat_desc
                                              ,TRIM( TO_CHAR(SUM(NVL(LOSS_RESERVE,0)*j.CONVERT_RATE),'999,999,999.99'))LOSS_RESERVE,h.grp_iss_cd
                                        FROM gicl_claims a, giac_direct_claim_payts b, giac_acctrans c, giac_chk_disbursement d
                                            ,giac_chk_release_info e,giac_disb_vouchers f, giis_users g
                                            ,giis_user_grp_hdr h, giis_clm_stat i, GICL_CLM_RES_HIST j 
                                        WHERE a.claim_id = b.claim_id AND b.gacc_tran_id = c.tran_id 
                                          AND c.tran_flag IN ('C', 'P') 
                                          AND a.claim_id = j.claim_id(+) 
                                          AND b.gacc_tran_id = d.gacc_tran_id(+) 
                                          AND b.gacc_tran_id = e.gacc_tran_id(+) 
                                          AND b.gacc_tran_id = f.gacc_tran_id(+) 
                                          AND a.in_hou_adj = g.user_id 
                                          AND g.user_grp = h.user_grp 
                                          AND h.grp_iss_cd = '$branch'
                                          AND h.grp_iss_cd NOT IN ('MN')
                                          AND a.clm_stat_cd = i.clm_stat_cd 
                                          AND a.clm_stat_cd NOT IN ('CD', 'CC', 'WD', 'DN') 
                                        GROUP BY get_claim_number (a.claim_id), loss_date
                                                ,assured_name, replenished_tag
                                                ,in_hou_adj,clm_stat_desc,a.claim_id ,a.line_cd,grp_iss_cd
                                          HAVING calculate_business_days (MAX (check_date), SYSDATE) >= $aging
                                        )GROUP BY LINE_CD, grp_iss_cd
                                     )C
                                   WHERE A.BRANCH = B.BRANCH(+)
                                     AND A.LINE_CD = B.LINE_CD(+)
                                     AND A.BRANCH = C.BRANCH(+)
                                     AND A.LINE_CD = C.LINE_CD(+)
                                     )");
        oci_execute($Totalresult);
        while ($row = oci_fetch_assoc($Totalresult)) {
            $SUM_NON   = $row['NON_MOVING'];
            $SUM_OPEN  = $row['OPEN_CLAIMS'];
            $SUM_PERC  = $row['PERCENTAGE'];
            $SUM_PAID  = $row['PAID_STILL_OPEN'];
            
            $type1 = "";
            $type2 = "";
            $type3 = "";
            
            if($SUM_NON == 0){
                $type1 = $SUM_NON;
            }else{
                $type1 = "<a href = http://10.20.39.122/test/Claims/ClaimSchedDetail.php?branch=$branch>$SUM_NON</a>";
            }
            
            if($SUM_OPEN == 0){
                $type2 = $SUM_OPEN;
            }else{
                $type2 = "<a href = http://10.20.39.122/test/Claims/ClaimSchedOpen.php?branch=$branch&param=$param>$SUM_OPEN</a>";
            }
            
            if($SUM_PAID == 0){
                $type3 = $SUM_PAID;
            }else{
                $type3 = "<a href = http://10.20.39.122/test/Claims/ClaimSchedOpenPaid.php?branch=$branch>$SUM_PAID</a>";
            }
            
            $message .= "<td align=right><b>$type1</b></td>
                         <td align=right><b>$type2</b></td>
                         <td align=right><b>$SUM_PERC %</b></td>
                         <td align=right><b>$type3</b></td>
                    </tr>";
        }

        
        $message .= "</table>";
        $all = $message1.$message.$message2;
        echo $all;
        
        $email = new Email();
        $email->setFrom("NonMovingClaimsNoReply@charterpingan.com");
        $email->setToList($email_add);
        $email->setCcList($cc);
        $email->setSubject("Subject: Claim Aging for the month of ".$day_month."-".$day_yy);
        //$email->setToList('gerald.viray@axa.com.ph');
        //$email->setCcList('gerald.viray@axa.com.ph','rose.banaag@axa.com.ph');
        // $email->setSubject("Subject: Claim Aging for the month of July - ".$day_yy);
        $email->setContent("Content: ");
        $email->setHtmlContent($all);
        $email->addCustomHeader('X-FirstHeader', "value");
        $email->addCustomHeader('X-SecondHeader', "value");
        $email->addCustomHeader('X-Header-da-rimuovere', 'value');
        $email->removeCustomHeader('X-Header-da-rimuovere');
        $turboApiClient = new TurboApiClient("_username", "_password");
        $response = $turboApiClient->sendEmail($email);
       // var_dump($response);
    }

?>
