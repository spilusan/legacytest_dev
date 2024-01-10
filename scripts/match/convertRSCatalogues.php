<?php
/**
 * An ad-hoc script to convert RS Catalogue files available from http://dev.shipserv.com/AjaXplorer to lists of automatch keywords
 *
 * @author	Yuriy Akopov
 * @date	2014-09-11
 * @story	S11323
 */

const MAX_PROGRAM_LINES = null; // null if no need to split SQL files, or a number of MERGE lines to put in one file

// open source file
$fileParam = $argv[1];
if (!file_exists($fileParam)) {
	throw new Exception("Input CSV file " . $fileParam . " or folder not found!");
}

if (is_dir($fileParam)) {
    $dh = opendir($fileParam);

    while (false !== ($filename = readdir($dh))) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if ($ext === 'csv') {
            $csvFiles[] = $filename;
        }
    }

} else {
    $csvFiles = array($fileParam);
}

foreach ($csvFiles as $csvFilename) {
    convertCsv($csvFilename);
}

/**
 * @param   string  $csvFilename
 * @throws  Exception
 */
function convertCsv($csvFilename) {
    print "Processing file " . $csvFilename . "..." . PHP_EOL;
    $keywordSetName = basename($csvFilename, '.csv');

    $keywordSetName = 'RS ' . $keywordSetName;

    // open the source file
    $csvFile = fopen($csvFilename, 'r');

    // prepare SQL for loading list ID by its name
    $output = array(
        'partNo' => array(
            'fh'       => null,
            'column'   => 10,
            'rowCount' => 0
        ),
        'partNoMft' => array(
            'fh'       => null,
            'column'   => 20,
            'rowCount' => 0
        ),
        'desc' => array(
            'fh'       => null,
            'column'   => 11,
            'rowCount' => 0
        )
    );

    // skip CSV header lines
    $csvRowNo = 0;

    while (($row = fgetcsv($csvFile)) !== false) {
        $csvRowNo++;

        // source file is expected to have 4 irrelevant top rows we need to skip
        if ($csvRowNo <= 4) {
            continue;
        }

        foreach ($output as $type => $fileInfo) {
            if (is_null($fileInfo['fh'])) {
                $output[$type]['fh'] = startSqlFile($keywordSetName, $type);
            }

            if (writeInsertSql($output[$type]['fh'], $keywordSetName, $row[$fileInfo['column']])) {
                $output[$type]['rowCount']++;
            }

            if (!is_null(MAX_PROGRAM_LINES)) {
                if ($output[$type]['rowCount'] > MAX_PROGRAM_LINES) {
                    endSqlFile($fileInfo['fh']);
                    $output[$type]['fh'] = startSqlFile($keywordSetName, $type);
                    $output[$type]['rowCount'] = 0;
                }
            }
        }
    }

    foreach ($output as $type => $fileInfo) {
        if (!is_null($fileInfo['fh'])) {
            endSqlFile($fileInfo['fh']);
        }
    }
}

/**
 * @param   resource    $fh
 */
function endSqlFile($fh) {
    // we no longer use variables due to program length limitations they impose, hence no need to BEGIN/END block
    /*
    fwrite($fh, "END;" . PHP_EOL);
    fwrite($fh, "/" . PHP_EOL);
    */

    fclose($fh);
}

/**
 * Creates a new SQL file
 *
 * @param   string  $keywordSetName
 * @param   string  $fileTypeStr
 *
 * @return resource
 */
function startSqlFile($keywordSetName, $fileTypeStr) {
    // @todo: by this we support only up to 999 files, but that should be more than enough
    $index = '001';
    $postfixLen = strlen($index);

    while(file_exists($keywordSetName . '.' . $fileTypeStr . '.' . $index . '.sql')) {
        $indexNo = (int) $index;
        $indexNo++;

        $index = (string) $indexNo;

        while (strlen($index) < $postfixLen) {
            $index = '0' . $index;
        }
    }

    // create a fix with a filename that doesn't exist yet
    $fh = fopen($keywordSetName . '.' . $fileTypeStr . '.' . $index . '.sql', 'w');

    $safeKeywordSetName = escapeStr($keywordSetName);
    $sql = implode(PHP_EOL, array(
        "-- this is to disable SQL Developer special characters such as &",
        "SET SCAN OFF;",
        "SET DEFINE OFF;",
        "-- create a new keyword set if it doesn't exist yet",
        "MERGE INTO MATCH_SUPPLIER_KEYWORD_SET USING DUAL ON (MSS_NAME = '" . $safeKeywordSetName . "') WHEN NOT MATCHED THEN INSERT (MSS_NAME) VALUES('" . $safeKeywordSetName . "');",
        // we no longer use variables due to program length limitations they impose, hence no need to BEGIN/END block
        /*
        "-- load keyword set ID to use in following INSERTs",
        "DECLARE keywordSetId NUMBER(15, 0);",
        "BEGIN",
        "SELECT MSS_ID INTO keywordSetId FROM MATCH_SUPPLIER_KEYWORD_SET WHERE MSS_NAME = '" . $safeKeywordSetName . "';",
        */
        "-- every line below represents one row from RS catalogue",
        ""
    ));

    fwrite($fh, $sql);

    return $fh;
}

/**
 * Writes an INSERT statement into a given file to add a record for the given keyword
 *
 * @param   resource    $file
 * @param   string      $keywordSetName
 * @param   string      $keyword
 *
 * @return  bool
 */
function writeInsertSql($file, $keywordSetName, $keyword) {
    $safeKeyword = escapeStr($keyword);

    if ((strlen($safeKeyword) === 0) or (strlen($safeKeyword) >= 255)) {
        return false;
    }

    fwrite($file,

        // the code below was based on using variables which used to impose a limit on the number of commands per file (BEGIN/END block)
        // a more clunky query replacing the one below does not need variables
        /*
        "MERGE INTO MATCH_SUPPLIER_KEYWORD USING DUAL ON (MSK_MSS_ID = keywordSetId AND LOWER(MSK_KEYWORD) = LOWER('" . $keyword . "')) "  .
        "WHEN NOT MATCHED THEN INSERT (MSK_MSS_ID, MSK_KEYWORD) VALUES(keywordSetId, '" . $keyword . "');" .
        */
        implode(" ", array(
            "MERGE INTO match_supplier_keyword msk",
            "USING (SELECT * FROM match_supplier_keyword_set WHERE mss_name = '" . escapeStr($keywordSetName) . "') mss ON (",
                "msk.msk_mss_id = mss.mss_id",
                "AND LOWER(msk.msk_keyword) = LOWER('" . $safeKeyword . "')",
            ")",
            "WHEN NOT MATCHED THEN INSERT (msk.msk_mss_id, msk.msk_keyword) VALUES(mss.mss_id, '" . $safeKeyword . "')",
            ";"
        )) . PHP_EOL
    );

    return true;
}

/**
 * Sanitises the string for SQL script to be run in SQL Developer
 *
 * @param   string  $value
 *
 * @return  string
 */
function escapeStr($value) {
    return str_replace("'", "''", trim($value));
}