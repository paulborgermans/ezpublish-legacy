<?php

//
// Created on: <01-Sep-2003 13:23:32 kk>
//
// Copyright (C) 1999-2004 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE.GPL included in
// the packaging of this file.
//
// Licencees holding valid "eZ publish professional licences" may use this
// file in accordance with the "eZ publish professional licence" Agreement
// provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" is available at
// http://ez.no/products/licences/professional/. For pricing of this licence
// please contact us via e-mail to licence@ez.no. Further contact
// information is available at http://ez.no/home/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*! \file ezpdftable.php
*/

include_once( 'lib/ezpdf/classes/class.ezpdf.php' );

define( 'EZ_PDF_LIB_NEWLINE', '<C:callNewLine>' );
define( 'EZ_PDF_LIB_SPACE', '<C:callSpace>' );
define( 'EZ_PDF_LIB_TAB', '<C:callTab>' );

define( 'EZ_PDF_LIB_PAGENUM', '#page' );
define( 'EZ_PDF_LIB_TOTAL_PAGENUM', '#total' );
define( 'EZ_PDF_LIB_HEADER_LEVEL', '#level' );
define( 'EZ_PDF_LIB_HEADER_LEVEL_INDEX', '#indexLevel' );

/*!
  \class eZPDFTable class.ezpdftable.php
  \ingroup eZPDF
  \brief eZPDFTable adds extra support for tables
*/

class eZPDFTable extends Cezpdf
{

    /**
     Constructor. This class is only used to encapsulate a table.
    */
    function eZPDFTable($paper='a4',$orientation='portrait')
    {
        $this->Cezpdf($paper, $orientation);
        $this->TOC = array();
        $this->KeywordArray = array();
        $this->PageCounter = array();
        $this->initFrameMargins();

        $this->ez['textStack'] = array();

        $this->PreStack = array();
        $this->initPreStack();
        $this->DocSpecification = array();
        $this->FrontpageID = null;
    }

    /*!
     * \private
     * Initialize footer and header frame margins. Called by constructor
     */
    function initFrameMargins()
    {
        $this->ezFrame = array();

        $config =& eZINI::instance( 'pdf.ini' );

        $this->ezFrame['header'] = array( 'y0' => $this->ez['pageHeight'],
                                          'leftMargin' => $config->variable( 'Header', 'LeftMargin' ),
                                          'rightMargin' => $config->variable( 'Header', 'RightMargin' ),
                                          'topMargin' => $config->variable( 'Header', 'TopMargin' ),
                                          'bottomMargin' => $config->variable( 'Header', 'BottomMargin' ) );
        $this->ezFrame['footer'] = array( 'y0' => $this->ez['bottomMargin'],
                                          'leftMargin' => $config->variable( 'Footer', 'LeftMargin' ),
                                          'rightMargin' => $config->variable( 'Footer', 'RightMargin' ),
                                          'topMargin' => $config->variable( 'Footer', 'TopMargin' ),
                                          'bottomMargin' => $config->variable( 'Footer', 'BottomMargin' ) );
    }

    /**
     Get the current Y offset
    */
    function yOffset()
    {
        return $this->y;
    }

    /**
     Get the current X offset
    */
    function xOffset()
    {
        $xOffset = $this->ez['xOffset'];
        if ( $xOffset == 0 ||
             $this->leftMargin() > $this->ez['xOffset'] )
        {
            $xOffset = $this->leftMargin();
        }
        return $xOffset;
    }

    function setXOffset( $xOffset )
    {
        if ( $xOffset > $this->ez['pageWidth'] - $this->rightMargin() )
        {
            $this->ez['xOffset'] = 0;
        }
        else
        {
            $this->ez['xOffset'] = $xOffset;
        }
    }

    /** add a table of information to the pdf document
     * $data is a two dimensional array
     * $cols (optional) is an associative array, the keys are the names of the columns from $data
     * to be presented (and in that order), the values are the titles to be given to the columns
     * $title (optional) is the title to be put on the top of the table
     *
     * $options is an associative array which can contain:
     * 'cellData' => array( <coord> => array( 'size' => array( <width>, <height>),
     *                                        'justification' => <left|right|center> ),
     *                      <coord>....)
     *            All non specified coords will be threated with default settings. Coord is text, offset 0, ex: '5,6'
     *            Coord 'x,0' is table header
     * 'showLines'=> 0,1,2, default is 1 (show outside and top lines only), 2=> lines on each row
     * 'showHeadings' => 0 or 1
     * 'shaded'=> 0,1,2,3 default is 1 (1->alternate lines are shaded, 0->no shading, 2-> both shaded, second uses shadeCol2)
     * 'shadeCol' => (CMYK) array, defining the colour of the shading
     * 'shadeCol2' => (CMYK) array, defining the colour of the shading of the other blocks
     * 'fontSize' => 10
     * 'textCol' => (CMYK) array, text colour
     * 'titleFontSize' => 12
     * 'rowGap' => 2 , the space added at the top and bottom of each row, between the text and the lines
     * 'colGap' => 5 , the space on the left and right sides of each cell
     * 'lineCol' => (r,g,b) array, defining the colour of the lines, default, black.
     * 'xPos' => 'left','right','center','centre',or coordinate, reference coordinate in the x-direction
     * 'xOrientation' => 'left','right','center','centre', position of the table w.r.t 'xPos'
     * 'width'=> <number> which will specify the width of the table, if it turns out to not be this
     *    wide, then it will stretch the table to fit, if it is wider then each cell will be made
     *    proportionalty smaller, and the content may have to wrap.
     * 'maxWidth'=> <number> similar to 'width', but will only make table smaller than it wants to be
     * 'options' => array(<colname>=>array('justification'=>'left','width'=>100,'link'=>linkDataName),<colname>=>....)
     *             allow the setting of other paramaters for the individual columns
     * 'minRowSpace'=> the minimum space between the bottom of each row and the bottom margin, in which a new row will be started
     *                  if it is less, then a new page would be started, default=-100
     * 'innerLineThickness'=>1
     * 'outerLineThickness'=>1
     * 'splitRows'=>0, 0 or 1, whether or not to allow the rows to be split across page boundaries
     * 'protectRows'=>number, the number of rows to hold with the heading on page, ie, if there less than this number of
     *                rows on the page, then move the whole lot onto the next page, default=1
     *
     * note that the user will have had to make a font selection already or this will not
     * produce a valid pdf file.
     */
    function ezTable(&$data,$cols='',$title='',$options='')
    {
        include_once( 'lib/ezutils/classes/ezmath.php' );

        if (!is_array($data)){
            return;
        }

        // Get total column count and column indexes
        if (!is_array($cols)){
            // take the columns from the first row of the data set
            reset($data);
            list($k,$v)=each($data);
            if (!is_array($v)){
                return;
            }
            $cols=array();

            $realCount = 0;
            for ( $c = 0; $c < count($v); $c++ )
            {
                if ( isset( $options['cellData'][$realCount.',0']['size'] ) )
                {
                    for ( $innerCount = 0; $innerCount < $options['cellData'][$realCount.',0']['size'][0]; $innerCount++ )
                        $cols[$realCount] = $realCount++;
                }
                else
                {
                    $cols[$realCount] = $realCount++;
                }
            }
        }

        if (!is_array($options)){
            $options=array();
        }

        $defaults = array(
            'cellPadding' => 0,
            'shaded' => 0,
            'showLines' => 1,
            'shadeCol' => eZMath::rgbToCMYK2( 0.8, 0.8, 0.8 ),
            'shadeCol2' => eZMath::rgbToCMYK2( 0.7, 0.7, 0.7 ),
            'fontSize' => 10,
            'titleFontSize' => 12,
            'titleGap' => 5,
            'lineCol' => array( 0, 0, 0 ),
            'gap' => 5,
            'xPos' => 'centre',
            'xOrientation' => 'centre',
            'showHeadings' => 1,
            'textCol' => eZMath::rgbToCMYK2( 0, 0, 0 ),
            'width' => 0,
            'maxWidth' => 0,
            'cols' => array(),
            'minRowSpace' => -100,
            'rowGap' => 2,
            'colGap' => 5,
            'innerLineThickness' => 1,
            'outerLineThickness' => 1,
            'splitRows' => 0,
            'protectRows'=> 1,
            'firstRowTitle' => false,
            'titleFontSize' => 10,
            'test' => 0,
            'yBottom' => 0 );

        foreach($defaults as $key=>$value){
            if (is_array($value)){
                if (!isset($options[$key]) || !is_array($options[$key])){
                    $options[$key]=$value;
                }
            } else {
                if (!isset($options[$key])){
                    $options[$key]=$value;
                }
            }
        }
        $options['gap']=2*$options['colGap'];
        $middle = ($this->ez['pageWidth']- $this->rightMargin() - $this->leftMargin() )/2+$this->leftMargin();
        // figure out the maximum widths of the text within each column
        $maxWidth = array();

        $maxRowCount = 0;
        // find the maximum cell widths based on the data
        foreach ( $data as $rowCount=>$row)
        {
            $realColCount = 0;
            for( $columnCount = 0; $columnCount < count( $row ); $columnCount++ )
            {
                // get col span
                $colSpan = 1;
                if ( isset( $options['cellData'][$realColCount.','.$rowCount]['size'] ) )
                {
                    $colSpan = $options['cellData'][$realColCount.','.$rowCount]['size'][0];
                }

                //get and set max width
                if ( ( $rowCount == 0 && $options['firstRowTitle'] ) ||
                     ( isset( $options['cellData'][$realColCount.','.$rowCount]['title'] ) && $options['cellData'][$realColCount.','.$rowCount]['title'] ) )
                {
                    $w = $this->ezPrvtGetTextWidth( $options['titleFontSize'], (string)$row[$columnCount] ) * 1.01;
                    $options['cellData'][$realColCount.','.$rowCount]['title'] = true;
                }
                else
                {
                    $w = $this->ezPrvtGetTextWidth( $options['fontSize'], (string)$row[$columnCount] ) * 1.01;
                }

                if ( isset( $maxWidth[$colSpan][$realColCount] ) )
                {
                    if ( $w > $maxWidth[$colSpan][$realColCount] )
                    {
                        $maxWidth[$colSpan][$realColCount] = $w;
                    }
                }
                else
                {
                    if ( !isset( $maxWidth[$colSpan] ) )
                    {
                        $maxWidth[$colSpan] = array();
                    }
                    $maxWidth[$colSpan][$realColCount] = $w;
                }

//                echo "row: $rowCount, column: $columnCount:$realColCount,<br/>";
                $realColCount += $colSpan;

                if ( $realColCount > $maxRowCount )
                {
                    $maxRowCount = $realColCount;
                }
            }
        }

        // calculate the start positions of each of the columns
        // Set pre defined max column width data
        for ( $columnCount = 0; $columnCount < count( $cols ); $columnCount++ ){
            if ( isset( $options['cols'][$columnCount] ) && isset($options['cols'][$columnCount]['width']) && $options['cols'][$colName]['width']>0)
            {
                $colSpan = 1;
                if ( isset( $options['cellData'][$realColCount.',0']['size'] ) )
                {
                    $colSpan = $options['cellData'][$realColCount.',0']['size'][0];
                }

                $maxWidth[$colSpan][$columnCount] = $options['cols'][$colName]['width'] - $options['gap'] - 2*$options['cellPadding'];
            }
        }

//        print_r( $maxWidth );
//        echo '<br/><br/>';

        $pos=array();
        $columnWidths = array();
        for ( $offset = 0; $offset < count( $cols ); )
            $columnWidths[$offset++] = 0;
        $x=0;
        $t=$x;
        $adjustmentWidth=0;
        $setWidth=0;
        foreach ( $maxWidth as $span => $tmp1 )
        {
            foreach ( $maxWidth[$span] as $offset => $tmp2 )
            {
                $currentWidth = 0;
                for ( $innerCount = 0; $innerCount < $span; $innerCount++ )
                {
                    $currentWidth += $columnWidths[$offset+$innerCount];
                }
                if ( $maxWidth[$span][$offset] > $currentWidth )
                {
                    if ( $currentWidth == 0 ) // no width set
                    {
                        for ( $i = 0; $i < $span; $i++ )
                            $columnWidths[$offset+$i] = ceil( $maxWidth[$span][$offset] / $span );
                    }
                    else // scale previous set widths
                    {
                        for ( $i = 0; $i < $span; $i++ )
                            $columnWidths[$offset+$i] = ceil( $maxWidth[$span][$offset] / $currentWidth * $columnWidths[$offset+$i] );
                    }
                }
            }
        }

//        print_r( $columnWidths );
//        echo '<br/><br/>';

        foreach ( $columnWidths as $count => $width )
        {
            $pos[$count]=$t;
            // if the column width has been specified then set that here, also total the
            $t += $width + $options['gap'] + 2*$options['cellPadding'];
            $adjustmentWidth += $width;
            $setWidth += $options['gap'] + 2*$options['cellPadding'];
        }
        $pos['_end_'] = $t;


//        print_r( $pos );
//        echo '<br/><br/>';

        // if maxWidth is specified, and the table is too wide, and the width has not been set,
        // then set the width.
        if ($options['width']==0 && $options['maxWidth'] && $pos['_end_']>$options['maxWidth']){
            // then need to make this one smaller
            $options['width']=$options['maxWidth'];
        }

        // calculate total table width before limiting
        $totalTableWidth = 0;
        foreach ( array_keys( $columnWidths ) as $idx ){
            $totalTableWidth += $columnWidths[$idx];
        }

        if ( $options['width'] == 0 &&
             $totalTableWidth > $this->ez['pageWidth'] - $this->leftMargin() - $this->rightMargin() )
        {
            $options['width'] = $this->ez['pageWidth'] - $this->leftMargin() - $this->rightMargin();
        }

        // calculated width as forced. Shrink or enlarge
        if ( $options['width'] && $adjustmentWidth>0 ){
            $newCleanWidth = $options['width'] - $setWidth;
            $t = 0;
            foreach ( $columnWidths as $count => $width )
            {
                $pos[$count] = $t;
                $columnWidths[$count] = round( $newCleanWidth/$adjustmentWidth * $columnWidths[$count] );
                $t += $columnWidths[$count] + $options['gap'] + 2*$options['cellPadding'];
            }
            $pos['_end_']=$t;
        }

        /* Calculate max column widths */
        foreach ( array_keys ( $maxWidth ) as $colspan )
        {
            foreach ( array_keys( $maxWidth[$colspan] ) as $offset )
            {
                $maxWidth[$colspan][$offset] = 0;
                for ( $columnCount = $offset; $columnCount < $offset + $colspan; $columnCount ++ )
                {
                    if ( $maxWidth[$colspan][$offset] != 0 )
                    {
                        $maxWidth[$colspan][$offset] += $options['gap'] + 2*$options['cellPadding'];
                    }
                    $maxWidth[$colspan][$offset] += $columnWidths[$columnCount];
                }
            }
        }

        // now adjust the table to the correct location across the page
        switch ($options['xPos']){
            case 'left':
                $xref = $this->leftMargin();
            break;
            case 'right':
                $xref = $this->ez['pageWidth'] - $this->rightMargin();
            break;
            case 'centre':
            case 'center':
                $xref = $middle;
            break;
            default:
                $xref = $options['xPos'];
            break;
        }
        switch ($options['xOrientation']){
            case 'left':
                $dx = $xref-$t;
            break;
            case 'right':
                $dx = $xref;
            break;
            case 'centre':
            case 'center':
                $dx = $xref-$t/2;
            break;
        }

        foreach($pos as $key=>$value){
            $pos[$key] += $dx;
        }
        $x0=$x+$dx;
        $x1=$t+$dx;

        $baseLeftMargin = $this->leftMargin();
        $basePos = $pos;
        $baseX0 = $x0;
        $baseX1 = $x1;

        // ok, just about ready to make me a table
        $this->setColor( $options['textCol'] );

        $middle = ($x1+$x0)/2;

        // start a transaction which will be used to regress the table, if there are not enough rows protected
        if ($options['protectRows']>0){
            $this->transaction('start');
            $movedOnce=0;
        }
        $abortTable = 1;

        if ( $options['yBottom'] > 0 ) // for aligning table to bottom
        {
            $options['test'] = 1;
        }

        while ($abortTable){
            $abortTable=0;

            $dm = $this->ez['leftMargin']-$baseLeftMargin;
            foreach($basePos as $key=>$value){
                $pos[$key] += $dm;
            }
            $x0=$baseX0+$dm;
            $x1=$baseX1+$dm;
            $middle = ($x1+$x0)/2;

            // margins may have changed on the newpage
            $dm = $this->ez['leftMargin']-$baseLeftMargin;
            foreach($basePos as $key => $value){
                $pos[$key] += $dm;
            }
            $x0=$baseX0+$dm;
            $x1=$baseX1+$dm;

            $y=$this->y; // to simplify the code a bit
            $startY = $y;

            // make the table
            $height = $this->getFontHeight($options['fontSize']);
            $decender = $this->getFontDecender($options['fontSize']);

            $y0=$y+$decender;
            $dy=0;
            if ($options['showHeadings']){
                // this function will move the start of the table to a new page if it does not fit on this one
                $headingHeight = $this->ezPrvtTableColumnHeadings($cols,$pos,$maxWidth,$height,$decender,$options['rowGap'],$options['fontSize'],$y,$options);
                $y0 = $y+$headingHeight;
                $y1 = $y;

                $dm = $this->leftMargin()-$baseLeftMargin;
                foreach($basePos as $k=>$v){
                    $pos[$k]=$v+$dm;
                }
                $x0=$baseX0+$dm;
                $x1=$baseX1+$dm;

            } else {
                $y1 = $y0;
            }
            $firstLine=1;


            // open an object here so that the text can be put in over the shading
            if (!$options['test'] && $options['shaded']){
                $this->saveState();
                $textObjectId = $this->openObject();
                $this->closeObject();
                $this->addObject($textObjectId);
                $this->reopenObject($textObjectId);
            }

            $cnt=0;
            $newPage=0;

            foreach($data as $rowCount => $row){
                $cnt++;
                // the transaction support will be used to prevent rows being split
                if ($options['splitRows']==0){
                    $pageStart = $this->ezPageCount;
                    if (isset($this->ez['columns']) && $this->ez['columns']['on']==1){
                        $columnStart = $this->ez['columns']['colNum'];
                    }
                    if ( !$options['test'] )
                    {
                        $this->transaction('start');
                    }
                    $row_orig = $row;
                    $y_orig = $y;
                    $y0_orig = $y0;
                    $y1_orig = $y1;
                }
                $ok=0;
                $secondTurn=0;
                while(!$abortTable && $ok == 0){

                    $mx=0;
                    $newRow=1;
                    while(!$abortTable && ($newPage || $newRow)){

                        if ($newPage || $y<$this->ez['bottomMargin'] || (isset($options['minRowSpace']) && $y<($this->ez['bottomMargin']+$options['minRowSpace'])) ){
                            // check that enough rows are with the heading
                            if ($options['protectRows']>0 && $movedOnce==0 && $cnt<=$options['protectRows']){
                                // then we need to move the whole table onto the next page
                                $movedOnce = 1;
                                $abortTable = 1;
                            }

                            $y2=$y-$mx+2*$height+$decender-$newRow*$height;
                            if ($options['showLines']){
                                if (!$options['showHeadings']){
                                    $y0=$y1;
                                }
                            }
                            if ($options['shaded']){
                                $this->closeObject();
                                $this->restoreState();
                            }
                            $this->ezNewPage();
                            // and the margins may have changed, this is due to the possibility of the columns being turned on
                            // as the columns are managed by manipulating the margins

                            $dm = $this->leftMargin()-$baseLeftMargin;
                            foreach($basePos as $k=>$v){
                                $pos[$k]=$v+$dm;
                            }

                            $x0=$baseX0+$dm;
                            $x1=$baseX1+$dm;

                            if (!$options['test'] && $options['shaded']){
                                $this->saveState();
                                $textObjectId = $this->openObject();
                                $this->closeObject();
                                $this->addObject($textObjectId);
                                $this->reopenObject($textObjectId);
                            }
                            $this->setColor( $options['textCol'], 1 );
                            $y = $this->ez['pageHeight']-$this->ez['topMargin'];
                            $y0=$y+$decender;
                            $mx=0;
                            if ($options['showHeadings']){
                                $this->ezPrvtTableColumnHeadings($cols,$pos,$maxWidth,$height,$decender,$options['rowGap'],$options['fontSize'],$y,$options);
                                $y1=$y;
                            } else {
                                $y1=$y0;
                            }
                            $firstLine=1;
                            $y -= $height;
                        }
                        $newRow=0;
                        // write the actual data
                        // if these cells need to be split over a page, then $newPage will be set, and the remaining
                        // text will be placed in $leftOvers
                        $newPage=0;
                        $leftOvers=array();

                        $realColumnCount = 0;

                        for ( $columnCount = 0; $columnCount < count ( $row ); $columnCount++ )
                        {
                            // Get colSpan
                            if ( isset( $options['cellData'][$realColumnCount.','.$rowCount]['size'] ) )
                            {
                                $colSpan = $options['cellData'][$realColumnCount.','.$rowCount]['size'][0];
                            }
                            else
                            {
                                $colSpan = 1;
                            }

                            $this->ezSetY($y+$height);
                            $colNewPage=0;

                            $lines = explode("\n",$row[$columnCount]);
                            $this->y -= $options['rowGap'];
                            foreach ($lines as $line){
                                $line = $this->ezProcessText($line);
                                $start=1;

                                while (strlen($line) || $start){
                                    $start=0;
                                    if (!$colNewPage){
                                        $this->y-=$height;
                                    }
                                    if ($this->y < $this->ez['bottomMargin']){
                                        $newPage=1;
                                        $colNewPage=1;
                                    }
                                    if ($colNewPage){
                                        if (isset($leftOvers[$realColumnCount])){
                                            $leftOvers[$realColumnCount].="\n".$line;
                                        } else {
                                            $leftOvers[$realColumnCount] = $line;
                                        }
                                        $line='';
                                    } else {
                                        if (isset($options['cols'][$realColumnCount]) && isset($options['cols'][$realColumnCount]['justification']) ){
                                            $just = $options['cols'][$realColumnCount]['justification'];
                                        } else {
                                            $just='left';
                                        }
                                        $storeY = $this->y;
                                        if ( isset( $options['cellData'][$realColumnCount.','.$rowCount] ) &&
                                             $options['cellData'][$realColumnCount.','.$rowCount]['title'] === true )
                                        {
                                            $this->setColor( $options['titleTextCMYK'] );
                                            $textInfo = $this->addTextWrap( $pos[$realColumnCount],
                                                                            $this->y,
                                                                            $maxWidth[$colSpan][$realColumnCount],
                                                                            $options['titleFontSize'],
                                                                            $line,
                                                                            $just,
                                                                            0,
                                                                            $options['test']);
                                        }
                                        else
                                        {
                                            $this->setColor( $options['textCol'], 1 );
                                            $textInfo = $this->addTextWrap($pos[$realColumnCount],$this->y,$maxWidth[$colSpan][$realColumnCount],$options['fontSize'],$line,$just,0,$options['test']);
                                        }
                                        $this->y = $storeY;
                                        $line=$textInfo['text'];
                                        if ( $textInfo['height'] != -1 )
                                        {
                                            $this->y -= $textInfo['height'];
                                        }
                                    }
                                }
                            }

                            $dy=$y-$this->y+$options['rowGap']+2*$options['cellPadding'];
                            if ($dy-$height*$newPage>$mx)
                            {
                                $mx=$dy-$height*$newPage;
                            }

                            $realColumnCount += $colSpan;

                        } // End for ( ... count( $row ) ... )

                        if ( !$options['test'] )
                        {
                            if ( isset( $options['cellData'][$realColumnCount.','.$rowCount] ) &&
                                 $options['cellData'][$realColumnCount.','.$rowCount]['title'] === true )
                            {
                                $shadeCol = $options['titleCellCMYK'];
                            }
                            else
                            {
                                if( $cnt % 2 == 0 )
                                {
                                    $shadeCol = $options['shadeCol'];
                                }
                                else
                                {
                                    $shadeCol = $options['shadeCol2'];
                                }
                            }

                            $rowHeight = $mx - $height + $decender;
                            $realColumnCount = 0;
                            for ( $columnCount = 0; $realColumnCount < $maxRowCount; $columnCount++ )
                            {
                                if ( isset( $options['cellData'][$realColumnCount.','.$rowCount]['size'] ) )
                                {
                                    $colSpan = $options['cellData'][$realColumnCount.','.$rowCount]['size'][0];
                                }
                                else
                                {
                                    $colSpan = 1;
                                }

                                if ( $options['shaded'] && $cnt % 2 == 0 )
                                {
                                    $this->closeObject();
                                    $this->setColor( $shadeCol );
                                    $this->filledRectangle($pos[$realColumnCount] - $options['cellPadding'],
                                                           $y,
                                                           $maxWidth[$colSpan][$realColumnCount] + 2*$options['cellPadding'],
                                                           -$rowHeight );
                                    $this->reopenObject($textObjectId);
                                }

                                if ($options['shaded']==2 && $cnt%2==1){
                                    $this->closeObject();
                                    $this->setColor( $shadeCol );
                                    $this->filledRectangle($pos[$realColumnCount] - $options['cellPadding'],
                                                           $y,
                                                           $maxWidth[$colSpan][$realColumnCount] + 2*$options['cellPadding'],
                                                           -$rowHeight );
                                    $this->reopenObject($textObjectId);
                                }

                                $realColumnCount += $colSpan;
                            }

                            // set $row to $leftOvers so that they will be processed onto the new page
                            $row = $leftOvers;
                            // now add the shading underneath
                            // Draw lines for each row and above
                            if ( $options['showLines'] > 0 )
                            {
                                $this->saveState();
                                $this->setStrokeColorRGB($options['lineCol'][0],$options['lineCol'][1],$options['lineCol'][2],1);

                                if ( $rowCount == 0 )
                                {
                                    $this->line( $x0-$options['gap']/2, $y+$decender+$height, $x1-$options['gap']/2, $y+$decender+$height );
                                }
                                $this->line( $x0-$options['gap']/2, $y+$decender+$height, $x0-$options['gap']/2, $y+$decender+$height-$mx );
                                $this->line( $x1-$options['gap']/2, $y+$decender+$height, $x1-$options['gap']/2, $y+$decender+$height-$mx );

                                if ( $options['showLines'] > 1 )
                                {
                                    // draw inner lines
                                    $this->line( $x0-$options['gap']/2, $y+$decender+$height-$mx, $x1-$options['gap']/2, $y+$decender+$height-$mx );
                                    for ( $posOffset = 0; $posOffset < count( $pos ) - 2; )
                                    {
                                        $colSpan = 1;
                                        if ( isset( $options['cellData'][$posOffset.','.$rowCount]['size'] ) )
                                        {
                                            $colSpan = $options['cellData'][$posOffset.','.$rowCount]['size'][0];
                                        }
                                        $this->line( $pos[$posOffset+$colSpan]-$options['gap']/2, $y+$decender+$height,
                                                     $pos[$posOffset+$colSpan]-$options['gap']/2, $y+$decender+$height-$mx );
                                        $posOffset += $colSpan;
                                    }
                                }
                                else if ( $rowCount == count( $data ) - 1 )
                                {
                                    $this->line( $x0-$options['gap']/2, $y+$decender+$height-$mx, $x1-$options['gap']/2, $y+$decender+$height-$mx );
                                }
                                $this->restoreState();
                            }
                            if ($options['showLines']>1){
                                $this->saveState();
                                $this->setStrokeColorRGB($options['lineCol'][0],$options['lineCol'][1],$options['lineCol'][2],1);

                                if ($firstLine){
                                    $this->setLineStyle($options['outerLineThickness']);
                                    $firstLine=0;
                                } else {
                                    $this->setLineStyle($options['innerLineThickness']);
                                }
                                $this->line($x0-$options['gap']/2,$y+$decender+$height,$x1-$options['gap']/2,$y+$decender+$height);
                                $this->restoreState();
                            }
                        }
                    } // end of while
                    $y=$y-$mx+$height;

                    // checking row split over pages
                    if ( $options['splitRows'] == 0 )
                    {
                        if ( ( ($this->ezPageCount != $pageStart) || (isset($this->ez['columns']) && $this->ez['columns']['on']==1 && $columnStart != $this->ez['columns']['colNum'] ))  && $secondTurn==0){
                            // then we need to go back and try that again !
                            $newPage=1;
                            $secondTurn=1;
                            $this->transaction('rewind');
                            $row = $row_orig;
                            $y = $y_orig;
                            $y0 = $y0_orig;
                            $y1 = $y1_orig;
                            $ok=0;

                            $dm = $this->leftMargin()-$baseLeftMargin;
                            foreach($basePos as $k=>$v){
                                $pos[$k]=$v+$dm;
                            }
                            $x0=$baseX0+$dm;
                            $x1=$baseX1+$dm;

                        } else {
                            $this->transaction('commit');
                            $ok=1;
                        }
                    }
                    else
                    {
                        $ok=1;  // don't go round the loop if splitting rows is allowed
                    }

                }  // end of while to check for row splitting
                if ($abortTable){
                    if ($ok==0){
                        $this->transaction('abort');
                    }
                    // only the outer transaction should be operational
                    $this->transaction('rewind');
                    $this->ezNewPage();
                    break;
                }

            } // end of foreach ($data as $row)

            if ( isset ( $options['yBottom'] ) && $options['yBottom'] > 0 )
            {
                $tableHeight = $startY - $y;
                $this->y = $options['yBottom'] + $tableHeight;
                unset( $options['yBottom'] );
                $options['test'] = 0;
                $this->transaction('rewind');
//                $this->transaction('start');
                $abortTable = 1;
                continue;
            }

        } // end of while ($abortTable)

        // table has been put on the page, the rows guarded as required, commit.
        $this->transaction('commit');

        $y2=$y+$decender;
        if ($options['showLines']){
            if (!$options['showHeadings']){
                $y0=$y1;
            }
//            $this->ezPrvtTableDrawLines($pos,$options['gap'],$x0,$x1,$y0,$y1,$y2,$options['lineCol'],$options['innerLineThickness'],$options['outerLineThickness'],$options['showLines']);
        }

        // close the object for drawing the text on top
        if ($options['shaded']){
            $this->closeObject();
            $this->restoreState();
        }

        $this->y=$y;
        return $y;
    }

    function ezPrvtTableDrawLines($pos,$gap,$x0,$x1,$y0,$y1,$y2,$col,$inner,$outer,$opt=1){
        $x0=1000;
        $x1=0;
        $this->setStrokeColorRGB($col[0],$col[1],$col[2]);
        $cnt=0;
        $n = count($pos);
        foreach($pos as $x){
            $cnt++;
            if ($cnt==1 || $cnt==$n){
                $this->setLineStyle($outer);
            } else {
                $this->setLineStyle($inner);
            }
            $this->line($x-$gap/2,$y0,$x-$gap/2,$y2);
            if ($x>$x1){ $x1=$x; };
            if ($x<$x0){ $x0=$x; };
        }
        $this->setLineStyle($outer);
        $this->line($x0-$gap/2-$outer/2,$y0,$x1-$gap/2+$outer/2,$y0);
        // only do the second line if it is different to the first, AND each row does not have
        // a line on it.
        if ($y0!=$y1 && $opt<2){
            $this->line($x0-$gap/2,$y1,$x1-$gap/2,$y1);
        }
        $this->line($x0-$gap/2-$outer/2,$y2,$x1-$gap/2+$outer/2,$y2);
    }

    /**
     * Callback function to set anchor
     */
    function callAnchor( $info )
    {
        $paramArray = explode( ':', $info['p'] );

        $this->addDestination( $paramArray[0], $paramArray[1], $this->yOffset() + $this->getFontHeight( $this->fontSize ) );
    }

    /**
     * Callback function to set header
     */
    function callHeader( $params )
    {
        $options = array();

        if ( isset( $params['size'] ) )
        {
            $options['fontSize'] = $params['size'];
        }

        if ( isset( $params['justification'] ) )
        {
            $options['justification'] = $params['justification'];
        }

        if ( isset( $params['fontName'] ) )
        {
            $options['fontName'] = 'lib/ezpdf/classes/fonts/'. $params['fontName'];
        }

        $this->addToPreStack( $options );

        $label = $params['label'];
        $level = $params['level'];

        return '<C:callInsertTOC:'. $label .','. $level .'>';
    }

    /**
     * Function for insert image
     */
    function callImage( $info )
    {
        $params = array();
        $leftMargin = false;
        $rightMargin = false;

        $this->extractParameters( $info['p'], 0, $params, true );

        $filename = rawurldecode( $params['src'] );

        include_once( 'lib/ezutils/classes/ezmimetype.php' );
        $mimetype = eZMimeType::findByFileContents( $filename );

        $this->transaction( 'start' );

        if ( $this->yOffset()-$params['height'] < $this->ez['bottomMargin'] )
        {
            $this->ezNewPage();
        }

        if ( isset( $params['dpi'] ) )
        {
            include_once( 'kernel/common/image.php' );

            $newWidth = (int)( $params['width'] * ( (int)$params['dpi'] / 72 ) );
            $newHeight = (int)( $params['height'] * ( (int)$params['dpi'] / 72 ) );
            $newFilename = eZSys::cacheDirectory() . '/' . md5( mt_rand() ) . '.jpg';
            while( file_exists( $newFilename ) )
            {
                $newFilename = eZSys::cacheDirectory() . '/' . md5( mt_rand() ) . '.jpg';
            }
            $img =& imageInit();
            $newImg = $img->convert( $filename,
                                     $newFilename,
                                     false,
                                     array( 'filters' => array( array( 'name' => 'geometry/scaledownonly',
                                                                       'data' => array( $newWidth, $newHeight ) ) ) ) );
            $filename = $newFilename['url'];
        }

        switch( $params['align'] )
        {
            case 'right':
            {
                $xOffset = $this->ez['pageWidth'] - ( $this->rightMargin() + $params['width'] );
                $rightMargin = $this->rightMargin() + $params['width'];
            } break;

            case 'center':
            {
                $xOffset = ( $this->ez['pageWidth'] - $this->rightMargin() - $this->leftMargin() ) / 2 + $this->leftMargin() - $params['width'] / 2;
            } break;

            case 'left':
            default:
            {
                $xOffset = $this->leftMargin();
                $leftMargin = $this->leftMargin() + $params['width'];
            } break;
        }

        if ( isset( $params['x'] ) )
        {
            $xOffset = $params['x'];
            $leftMargin = false;
            $rightMargin = false;
        }

        $yOffset = $this->yOffset();
        $whileCount = 0;
        while ( $this->leftMargin( $yOffset ) > $xOffset &&
                ++$whileCount < 100 )
        {
            $yOffset -= 10;
        }

        $yOffset -= $params['height'];
        if ( isset( $params['y'] ) )
        {
            $yOffset = $params['y'];
        }

        switch( $mimetype['name'] )
        {
            case 'image/jpeg':
            {
                if ( $leftMargin !== false )
                {
                    $this->setLimitedLeftMargin( $yOffset - 7, $yOffset + $params['height'] + 2, $leftMargin + 7 );
                }
                if ( $rightMargin !== false )
                {
                    $this->setLimitedRightMargin( $yOffset- 7, $yOffset + $params['height'] + 2, $rightMargin + 7 );
                }
                $this->addJpegFromFile( $filename,
                                        $xOffset,
                                        $yOffset,
                                        $params['width'],
                                        $params['height'] );
            } break;

            case 'image/png':
            {
                if ( $this->addPngFromFile( $filename,
                                            $xOffset,
                                            $yOffset,
                                            $params['width'],
                                            $params['height'] ) === false )
                {
                    $this->transaction('abort');
                    return;
                }
            } break;

            default:
            {
                eZDebug::writeError( 'Unsupported image file type, '. $mimetype['name'], 'eZPDFTable::callImage' );
                $this->transaction( 'abort' );
                return;
            } break;
        }

        $this->transaction( 'commit' );

        if ( !$leftMargin && !$rightMargin )
        {
            $this->y -= $params['height'];
        }

        return array( 'y' => $params['height'] );
    }


    /**
     * function for inserting keyword
     */
    function callKeyword( $info )
    {
        $keyWord = $this->fixWhitespace( rawurldecode( $info['p'] ) );
        $page = $this->ezWhatPageNumber($this->ezGetCurrentPageNumber());

        if ( !isset( $this->KeywordArray[$keyWord] ) )
        {
            $this->KeywordArray[$keyWord] = array();
        }

        if ( !isset( $this->KeywordArray[$keyWord][(string)$page] ) )
        {
            $label = $info['p'] .':'. $page;
            $this->KeywordArray[$keyWord][(string)$page] = array( 'label' => $label );

            $this->addDestination( 'keyword:'.$label,
                                   'FitH',
                                   $this->yOffset() );
        }
    }

    /**
     * function for inserting TOC
     */
    function callInsertTOC( $info )
    {
        $params = explode( ',', $info['p'] );

        $label = $params[0];
        $level = $params[1];

        $tocCount = count( $this->TOC );
        $this->TOC[] = array( 'label' => $this->fixWhitespace( rawurldecode( $label ) ),
                              'localPageNumber' => $this->ezWhatPageNumber( $this->ezGetCurrentPageNumber() ),
                              'level' => $level,
                              'pageNumber' => $this->ezGetCurrentPageNumber() );
        $this->addDestination( 'toc'. $tocCount,
                               'FitH',
                               $this->yOffset() + $this->getFontHeight( $this->fontSize() ) );
    }

    /**
     * Callback function for inserting TOC
     */
    function callTOC( $info )
    {
        $params = array();

        $this->extractParameters( $info['p'], 0, $params, true );

        $sizes = isset( $params['size'] ) ? explode( ',', $params['size'] ) : '';
        $indents = isset( $params['indent'] ) ? explode( ',', $params['indent'] ) : '';
        $dots = isset( $params['dots'] ) ? $params['dots'] : '';
        $contentText = isset( $params['contentText'] ) ? $params['contentText'] : ezi18n( 'lib/ezpdf/classes', 'Contents', 'Table of contents' );

        $this->insertTOC( $sizes, $indents, $dots, $contentText );
    }

    /**
     * Callback function for creating new page
     */
    function callNewPage( $info )
    {
        $this->ezNewPage();
    }

    function callIndex( $info )
    {
        $this->ezNewPage();
        $fontSize = $this->fontSize();
        Cezpdf::ezText( ezi18n( 'lib/ezpdf/classes', 'Index', 'Keyword index name' ) . '<C:callInsertTOC:Index,1>'."\n", 26, array('justification'=>'centre'));

        if ( count( $this->KeywordArray ) == 0 )
            return;

        ksort( $this->KeywordArray );
        reset( $this->KeywordArray );

        $this->ezColumnsStart( array( 'num' => 2 ) );

        foreach( array_keys( $this->KeywordArray ) as $keyWord )
        {
            Cezpdf::ezText( $keyWord,
                            $fontSize,
                            array( 'justification' => 'left' ) );

            foreach( array_keys( $this->KeywordArray[$keyWord] ) as $page )
            {
                Cezpdf::ezText( '<c:ilink:keyword:'. $this->KeywordArray[$keyWord][$page]['label'] .'> '. $page .'</c:ilink>',
                                $fontSize,
                                array( 'justification' => 'right' ) );
            }
        }

        $this->ezColumnsStop();
        $this->setFontSize( $fontSize );
    }

    /*!
     Create Table Of Contents (TOC)

     \param size array, element 0 define size of header level 1, etc.
     \param indent, element 0 define indent of header level 1, etc.
     \param dots, if true, generate dots between name and pagenumber
     \param content text
     \param level, how many header levels to generate toc form
    */
    function insertTOC( $sizeArray = array( 20, 18, 16, 14, 12 ),
                        $indentArray = array( 0, 4, 6, 8, 10 ),
                        $dots = true,
                        $contentText = '',
                        $level = 2 )
    {
        $fontSize = $this->fontSize();
        $this->ezStopPageNumbers(1,1);

        $this->ezInsertMode(1,1,'before');
        $this->ezNewPage();
        Cezpdf::ezText( $contentText ."\n", 26, array('justification'=>'centre'));

        foreach($this->TOC as $k=>$v){
            if ( $v['level'] <= $level )
            {
                if ( $dots )
                {
                    Cezpdf::ezText('<c:ilink:toc'. $k .'>'. $v['label'] .'</c:ilink><C:dots:'. $sizeArray[$v['level']-1].$v['localPageNumber'] .'>'."\n",
                                   $sizeArray[$v['level']-1],
                                   array('left' => $indentArray[$v['level']-1] ) );
                }
                else
                {
                    Cezpdf::ezText( '<c:ilink:toc'. $k .'>'.$v['label'].'</c:ilink>',
                                    $sizeArray[$v['level']-1],
                                    array( 'left' => $indentArray[$v['level']-1] ) );
                    Cezpdf::ezText( '<c:ilink:toc'. $k .'>'. $v['localPageNumber'] .'</c:ilink>',
                                    $sizeArray[$v['level']-1],
                                    array( 'justification' => 'right' ) );
                }
            }
        }

        $this->setFontSize( $fontSize );
        $this->ezInsertMode(0);
    }

    function dots($info){
        // draw a dotted line over to the right and put on a page number
        $tmp = $info['p'];
        $size = substr($tmp, 0, 2);
        $thick=1;
        $lbl = substr($tmp,2);
        $xpos = $this->ez['pageWidth'] - $this->rightMargin() - $this->leftMargin();

        $this->saveState();
        $this->setLineStyle($thick,'round','',array(0,10));
        $this->line($xpos,$info['y'],$info['x']+5,$info['y']);
        $this->restoreState();
        $this->addText($xpos+5,$info['y'],$size,$lbl);
        $this->ez['xOffset'] = $xpos+5+$this->getTextWidth($lbl, $size);
    }

    /**
     * Callback function to set font
     */
    function callFont( $params ){

        $options = array();

        $keyArray = array ( 'c', 'm', 'y', 'k' );
        if ( isset( $params['cmyk'] ) )
        {
            $params['cmyk'] = explode( ',', $params['cmyk'] );
            foreach ( array_keys( $params['cmyk'] ) as $key )
            {
                $options['cmyk'][$keyArray[$key]] = $params['cmyk'][$key];
            }
            $this->setStrokeColor( $params['cmyk'] );
        }

        if ( isset( $params['name'] ) )
        {
            $options['fontName'] = 'lib/ezpdf/classes/fonts/'. $params['name'];
        }

        if ( isset( $params['size'] ) )
        {
            $options['fontSize'] = $params['size'];
        }

        if ( isset( $params['justification'] ) )
        {
            $options['justification'] = $params['justification'];
        }

        $this->addToPreStack( $options );

        return '';
    }

    function &fixWhitespace( &$text )
    {
        return str_replace( array( EZ_PDF_LIB_SPACE,
                                   EZ_PDF_LIB_TAB,
                                   EZ_PDF_LIB_NEWLINE ),
                            array( ' ',
                                   "\t",
                                   "\n" ),
                            $text );
    }

    /**
     * Function overriding the default ezText function for doing preprocessing of text
     */
    function ezText( $text, $size=0, $options=array(), $test=0)
    {
        $text = $this->fixWhitespace( $text );

        $textLen = strlen( $text );
        $newText = '';
        for ( $offSet = 0; $offSet < $textLen; $offSet++ )
        {
            if ( $text[$offSet] == '<' )
            {
                if ( strcmp( substr($text, $offSet+1, strlen( 'ezCall' ) ), 'ezCall' ) == 0 ) // ez library preprocessing call.
                {
                    $newTextLength = strlen( $newText );
                    if ( $newTextLength > 0 && $newText[$newTextLength - 1] == "\n" )
                    {
                        unset( $newText[$newTextLength - 1] );
                        $this->addDocSpecification( $newText );
                        $newText = "\n";
                    }
                    else
                    {
                        $this->addDocSpecification( $newText );
                        $newText = '';
                    }

                    $params = array();
                    $funcName = '';

                    $offSet = $this->extractFunction( $text, $offSet, $funcName, $params, 'ezCall' );

                    $newText .= $this->$funcName( $params );

                    continue;
                }
                else if ( strcmp( substr($text, $offSet+1, strlen( '/ezCall' ) ), '/ezCall' ) == 0 )
                {
                    $this->addDocSpecification( $newText );
                    array_pop( $this->PreStack );
                    $offSet = strpos( $text, '>', $offSet );
                    $newText = '';
                    continue;
                }
                else if ( strcmp( substr($text, $offSet+1, strlen( 'ezGroup' ) ), 'ezGroup' ) == 0 ) // special call for processing whole text group, used by extends table.
                {
                    $newTextLength = strlen( $newText );
                    if ( $newTextLength > 0 && $newText[$newTextLength - 1] == "\n" )
                    {
                        unset( $newText[$newTextLength - 1] );
                        $this->addDocSpecification( $newText );
                        $newText = "\n";
                    }
                    else
                    {
                        $this->addDocSpecification( $newText );
                        $newText = '';
                    }

                    $params = array();
                    $funcName = '';

                    $offSet = $this->extractFunction( $text, $offSet, $funcName, $params, 'ezGroup' );
                    $offSet++;
                    $endGroup = strpos( $text, '</ezGroup:', $offSet );
                    $groupText = substr( $text, $offSet, $endGroup - $offSet );

                    $this->$funcName( $params, $groupText );

                    $offSet = strpos( $text, '>', $endGroup );
                    continue;
                }
            }
            $newText .= $text[$offSet];
        }
        if ( strlen( $newText ) > 0 )
        {
            $this->addDocSpecification( $newText );
        }

        return $this->outputDocSpecification();
    }

    /*!
     Function for drawing rectangle in document

     \param parameters
    */
    function callRectangle( $info )
    {
        $params = array();

        $this->extractParameters( $info['p'], 0, $params, true );

        $keyArray = array ( 'c', 'm', 'y', 'k' );
        $cmykColor = explode( ',', $params['cmyk'] );

        foreach ( array_keys( $cmykColor ) as $key )
        {
            $cmykColor[$keyArray[$key]] = $cmykColor[$key];
            unset( $cmykColor[$key] );
        }

        $stackColor = $this->currentStrokeColour;
        $this->setStrokeColor( $cmykColor );

        if ( isset( $params['x'] ) )
        {
            $x1 = $params['x'];
            $x2 = $x1 + $params['width'];
        }
        if ( isset( $params['y'] ) )
        {
            $y1 = $params['y'];
            $y2 = $y1 + $params['height'];
        }

        if ( isset( $params['topY'] ) )
        {
            $y2 = $params['topY'];
            if ( $params['height'] > 0 )
            {
                $y1 = $params['topY'] - $params['height'];
            }
            else
            {
                $y1 = $this->yOffset() + $params['height'];
            }
        }

        $this->setLineStyle( $params['line_width'] );

        if ( $params['corner'] )
        {
            $factor = $params['corner'];
            $degree = 0;

            $this->line( $x1 + $factor, $y1, $x2 - $factor, $y1 );
            $this->line( $x2, $y1 + $factor, $x2, $y2 - $factor );
            $this->line( $x2 - $factor, $y2, $x1 + $factor, $y2 );
            $this->line( $x1, $y2 - $factor, $x1, $y1 + $factor );

            $this->curve( $x2 - $factor, $y1,
                          $x2, $y1 - $degree * $factor,
                          $x2 + $degree * $factor, $y1,
                          $x2, $y1 + $factor );
            $this->curve( $x2, $y2 - $factor,
                          $x2 + $degree * $factor, $y2,
                          $x2, $y2 + $degree * $factor,
                          $x2 - $factor, $y2 );
            $this->curve( $x1 + $factor, $y2,
                          $x1, $y2 + $degree * $factor,
                          $x1 - $degree * $factor, $y2,
                          $x1, $y2 - $factor );
            $this->curve( $x1, $y1 + $factor,
                          $x1 - $degree * $factor, $y1,
                          $x1, $y1 - $degree * $factor,
                          $x1 + $factor, $y1 );
        }
        else
        {
            $this->rectangle( $x1, $y1, $params['width'], $params['height'] );
        }

        $this->setColor( $stackColor );
    }

    /*!
     Set new margins
    */
    function callSetMargin( $info )
    {
        $options = array();

        $this->extractParameters( $info['p'], 0, $options, true );

        if ( isset( $options['left'] ) )
        {
            $this->ez['leftMargin'] = (float)$options['left'];
        }

        if ( isset( $options['right'] ) )
        {
            $this->ez['rightMargin'] = (float)$options['right'];
        }

        if ( isset( $options['bottom'] ) )
        {
            $this->ez['bottomMargin'] = (float)$options['bottom'];
        }

        if ( isset( $options['top'] ) )
        {
            $this->ez['topMargin'] = (float)$options['top'];
        }

        if ( isset( $options['x'] ) )
        {
            $this->ez['xOffset'] = (float)$options['x'];
        }

        if ( isset( $options['y'] ) )
        {
            $this->ez['yOffset'] = (float)$options['y'];
            $this->y = (float)$options['y'];
        }
    }

    /*!
     Draw filled circle
    */
    function callCircle( $info )
    {
        $params = array();

        $this->extractParameters( $info['p'], 0, $params, true );

        $keyArray = array ( 'c', 'm', 'y', 'k' );
        $cmykColor = explode( ',', $params['cmyk'] );

        foreach ( array_keys( $cmykColor ) as $key )
        {
            $cmykColor[$keyArray[$key]] = $cmykColor[$key];
            unset( $cmykColor[$key] );
        }

        $strokeStackColor = $this->currentStrokeColour;
        $this->setStrokeColor( $cmykColor );
        $stackColor = $this->currentColour;
        $this->setColor( $cmykColor );

        if ( $params['x'] == -1 )
        {
            $params['x'] = $this->xOffset() + $params['radius'];
        }
        if ( $params['y'] == -1 )
        {
            $params['y'] = $this->yOffset();
        }
        if ( isset( $params['yOffset'] ) )
        {
            if ( $params['yOffset'] == -1 )
            {
                $params['y'] += $this->getFontHeight( $this->fontSize() )/2;
            }
            else
            {
                $params['y'] += $params['yOffset'];
            }
        }

        $this->filledEllipse( $params['x'], $params['y'], $params['radius'] );
        if ( isset( $params['indent'] ) )
        {
            $this->setXOffset( $params['x'] + $params['radius'] + $params['indent'] );
        }
        else
        {
            $this->setXOffset( $params['x'] + $params['radius'] );
        }

        $this->setStrokeColor( $strokeStackColor );
        $this->setColor( $stackColor );
    }

    /*!
     Function for drawing filled rectangle in document

     \param params
    */
    function callFilledRectangle( $info )
    {
        $params = array();

        $this->extractParameters( $info['p'], 0, $params, true );

        $keyArray = array ( 'c', 'm', 'y', 'k' );
        $cmykTop = explode( ',', $params['cmykTop'] );
        $cmykBottom = explode( ',', $params['cmykBottom'] );

        foreach ( array_keys( $cmykBottom ) as $key )
        {
            $cmykBottom[$keyArray[$key]] = $cmykBottom[$key];
            unset( $cmykBottom[$key] );
            $cmykTop[$keyArray[$key]] = $cmykTop[$key];
            unset( $cmykTop[$key] );
        }

        $this->ezShadedRectangle( $params['x'], $params['y'], $params['width'], $params['height'], $cmykTop, $cmykBottom );
    }

    /*!
      Function for adding footer definition to PDF document. creates call on stack for ezInsertFooter

      \param parameters
      \text inside ezGroup Tags
    */
    function callFrame( $params, $text )
    {
        if ( strlen( $text ) > 0 )
        {
            $this->addDocSpecFunction( 'ezInsertFrame', array( $this->fixWhitespace( $text ), $params) );
        }
    }

    /*!
      Function for setting frame margins. Frames are used to define for example footer and header areas

      \param info, standard ezpdf callback function
    */
    function callFrameMargins( $info )
    {
        $params = array();
        $this->extractParameters( $info['p'], 0, $params, true );

        if( isset( $this->ezFrame[$params['identifier']] ) )
        {
            $this->ezFrame[$params['identifier']] = array_merge( $this->ezFrame[$params['identifier']],
                                                                 $params );
        }
        else
        {
            $this->ezFrame[$params['identifier']] = $params;
        }
    }

    /*!
      Insert footer/header into PDF document

      \param text
      \param text parameters
    */
    function ezInsertFrame( $text, $textParameters )
    {
        $size = $this->fontSize();
        if ( isset( $textParameters['size'] ) )
        {
            $size = $textParameters['size'];
        }

        $previousFont = $this->currentFont();
        if ( isset( $textParameters['font'] ) )
        {
            $this->selectFont( $textParameters['font'] );
        }

        $justification = $this->justification();
        if ( isset( $textParameters['justification'] ) )
        {
            $justification = $textParameters['justification'];
        }

        switch( $textParameters['location'] )
        {
            case 'footer':
            {
                $frameCoords =& $this->ezFrame['footer'];
            } break;

            case 'frame_header':
            {
                $frameCoords =& $this->ezFrame['header'];
            } break;

            default:
            {
                $frameCoords =& $this->ezFrame[0];
            } break;
        }

        foreach ( $this->ezPages as $pageNum => $pageID )
        {
            if ( $pageNum < $textParameters['pageOffset'] )
                continue;

            $frameText = $text; //Create copy of text
            if( $textParameters['page'] == 'even' &&
                $pageNum % 2 == 1 )
                continue;
            else if ( $textParameters['page'] == 'odd' &&
                      $pageNum % 2 == 0 )
                continue;

            $countIdentifier = '';
            if ( strstr( $frameText, EZ_PDF_LIB_PAGENUM ) !== false )
            {
                foreach ( array_keys( $this->PageCounter ) as $identifier )
                {
                    if ( $this->PageCounter[$identifier]['start'] <= $pageNum &&
                         $this->PageCounter[$identifier]['stop'] >= $pageNum )
                    {
                        $frameText = str_replace( EZ_PDF_LIB_PAGENUM,
                                                  $this->ezWhatPageNumber( $pageNum, $identifier ),
                                                  $frameText );

                        if ( strstr( $frameText, EZ_PDF_LIB_TOTAL_PAGENUM ) !== false )
                        {
                            $frameText = str_replace( EZ_PDF_LIB_TOTAL_PAGENUM,
                                                      $this->PageCounter[$identifier]['stop'] - $this->PageCounter[$identifier]['start'] + 1,
                                                      $frameText );
                        }
                    }
                }
            }

            for( $levelCount = 0; $levelCount < 9; $levelCount++ )
            {
                if ( strstr( $frameText, EZ_PDF_LIB_HEADER_LEVEL.$levelCount ) !== false )
                {
                    $frameText = str_replace( EZ_PDF_LIB_HEADER_LEVEL.$levelCount,
                                              $this->headerLabel( $pageNum, $levelCount ),
                                              $frameText );
                }

                if ( strstr( $frameText, EZ_PDF_LIB_HEADER_LEVEL_INDEX.$levelCount ) !== false )
                {
                    $frameText = str_replace( EZ_PDF_LIB_HEADER_LEVEL_INDEX.$levelCount,
                                              $this->headerIndex( $pageNum, $levelCount ),
                                              $frameText );
                }
            }

            $yOffset = $frameCoords['y0'] - $frameCoords['topMargin'];

            $yOffset -= $this->getFontHeight( $size );
            $xOffset = $frameCoords['leftMargin'];
            $pageWidth = $this->ez['pageWidth'] - $frameCoords['leftMargin'] - $frameCoords['rightMargin'];

            $this->reopenObject($pageID);

            $lines = explode( "\n", $frameText );
            foreach ( array_keys( $lines ) as $key )
            {
                $start=1;
                $line = $lines[$key];
                while (strlen($line) || $start){
                    $start = 0;
                    $textInfo = $this->addTextWrap( $xOffset, $yOffset, $pageWidth, $size, $line, $justification );
                    $line = $textInfo['text'];

                    if ( strlen( $line ) )
                    {
                        $yOffset -= $this->getFontHeight( $size );
                    }
                }
            }

            $this->closeObject();
        }

        $this->selectFont( $previousFont );
    }

    /*!
     * Function for inserting frontpage into document. Called by ezGroup specification
     *
     * \param parameters
     * \param text in ezGroup
     */
    function callFrontpage( $params, $text )
    {
        $this->addDocSpecFunction( 'insertFrontpage', array( $params, $text ) );
    }

    /*!
     * Insert front page
     */
    function insertFrontpage( $params, $text )
    {
        $this->saveState();
        $closeObject = false;
        if ( $this->FrontpageID == null )
        {
            $this->ezInsertMode(1,1,'before');
            $this->ezNewPage();
            $this->FrontpageID = $this->currentPage;
        }
        else if( $this->currentPage != $this->FrontpageID )
        {
            $this->reopenObject( $this->FrontpageID );
            $closeObject = true;
        }

        $fontSize = $this->fontSize();

        Cezpdf::ezText( $text, $params['size'], array( 'justification' => $params['justification'],
                                                       'top_margin' => $params['top_margin'] ) );

        $this->setFontSize( $fontSize );

        if ( $closeObject )
        {
            $this->closeObject();
        }
        $this->restoreState();
    }

    /*!
     * Function for generating table definition. Called by ezGroup specification
     *
     * \param parameters
     * \param text in ezGroup
     */
    function callTable( $params, $text )
    {
        $textLen = strlen( $text );
        $tableData = array();
        $cellData = array();
        $showLines = 2;
        $rowCount = 0;
        $columnCount = 0;

        $columnText = '';

        $keyArray = array ( 'c', 'm', 'y', 'k' );
        if ( isset( $params['titleCellCMYK'] ) )
        {
            $params['titleCellCMYK'] = explode( ',', $params['titleCellCMYK'] );
            foreach ( array_keys( $params['titleCellCMYK'] ) as $key )
            {
                $params['titleCellCMYK'][$keyArray[$key]] = $params['titleCellCMYK'][$key];
                unset( $params['titleCellCMYK'][$key] );
            }
        }

        if ( isset( $params['cellCMYK'] ) )
        {
            $params['cellCMYK'] = explode( ',', $params['cellCMYK'] );
            foreach ( array_keys( $params['cellCMYK'] ) as $key )
            {
                $params['cellCMYK'][$keyArray[$key]] = $params['cellCMYK'][$key];
                unset( $params['cellCMYK'][$key] );
            }
            $params['shaded'] = 2;
            $params['shadeCol'] = $params['cellCMYK'];
            $params['shadeCol2'] = $params['cellCMYK'];
        }

        if ( isset( $params['textCMYK'] ) )
        {
            $params['textCMYK'] = explode( ',', $params['textCMYK'] );
            foreach ( array_keys( $params['textCMYK'] ) as $key )
            {
                $params['textCMYK'][$keyArray[$key]] = $params['textCMYK'][$key];
                unset( $params['textCMYK'][$key] );
            }
            $params['textCol'] = $params['textCMYK'];
        }

        if ( isset( $params['titleTextCMYK'] ) )
        {
            $params['titleTextCMYK'] = explode( ',', $params['titleTextCMYK'] );
            foreach ( array_keys( $params['titleTextCMYK'] ) as $key )
            {
                $params['titleTextCMYK'][$keyArray[$key]] = $params['titleTextCMYK'][$key];
                unset( $params['titleTextCMYK'][$key] );
            }
            $params['titleTextCMYK'] = $params['titleTextCMYK'];
        }

        if ( isset( $params['showLines'] ) )
        {
            $showLines = $params['showLines'];
        }

        for ( $offSet = 0; $offSet < $textLen; $offSet++ )
        {
            if ( $text[$offSet] == '<' )
            {
                if ( strcmp( substr($text, $offSet+1, strlen( 'tr' ) ), 'tr' ) == 0 )
                {
                    $tableData[] = array();
                    $offSet++;
                    $offSet += strlen( 'tr' );
                    continue;
                }
                else if ( strcmp( substr($text, $offSet+1, strlen( 'td' ) ), 'td' ) == 0 )
                {
                    $tdParams = array();
                    $offSet++;
                    $offSet += strlen( 'td' );
                    $offSet = $this->extractParameters( $text, $offSet, $tdParams );

                    if ( count( $tdParams ) > 0 )
                    {
                        $cellData[$columnCount. ',' .$rowCount] = array();
                        if ( isset( $tdParams['colspan'] ) )
                        {
                            $cellData[$columnCount. ',' .$rowCount]['size'] = array( (int)$tdParams['colspan'], 1 );
                        }
                        if ( isset( $tdParams['align'] ) )
                        {
                            $cellData[$columnCount. ',' .$rowCount]['justification'] = array( $tdParams['align'], 1 );
                        }
                    }
                    continue;
                }
                else if ( strcmp( substr($text, $offSet+1, strlen( 'th' ) ), 'th' ) == 0 )
                {
                    $thParams = array();
                    $offSet++;
                    $offSet += strlen( 'th' );
                    $offSet = $this->extractParameters( $text, $offSet, $thParams );

                    $cellData[$columnCount. ',' .$rowCount] = array();
                    $cellData[$columnCount.','.$rowCount]['title'] = true;
                    if ( isset( $thParams['colspan'] ) )
                    {
                        $cellData[$columnCount. ',' .$rowCount]['size'] = array( (int)$thParams['colspan'], 1 );
                    }
                    if ( isset( $thParams['align'] ) )
                    {
                        $cellData[$columnCount. ',' .$rowCount]['justification'] = array( $thParams['align'], 1 );
                    }

                    continue;
                }
                else if ( strcmp( substr($text, $offSet+1, strlen( '/tr' ) ), '/tr' ) == 0 )
                {
                    $rowCount++;
                    $columnCount = 0;
                    $offSet++;
                    $offSet += strlen( '/tr' );
                    continue;
                }
                else if ( strcmp( substr($text, $offSet+1, strlen( '/td' ) ), '/td' ) == 0 )
                {
                    if ( $columnCount == 0 )
                    {
                        $tableData[$rowCount] = array();
                    }
                    $tableData[$rowCount][$columnCount] = $columnText;
                    $columnText = '';
                    $columnCount++;
                    $offSet++;
                    $offSet += strlen( '/td' );
                    continue;
                }
                else if ( strcmp( substr($text, $offSet+1, strlen( '/th' ) ), '/th' ) == 0 )
                {
                    if ( $columnCount == 0 )
                    {
                        $tableData[$rowCount] = array();
                    }
                    $tableData[$rowCount][$columnCount] = $columnText;
                    $columnText = '';
                    $columnCount++;
                    $offSet++;
                    $offSet += strlen( '/th' );
                    continue;
                }
            }
            $columnText .= $text[$offSet];
        }
        $this->addDocSpecFunction( 'ezTable', array( $tableData, '', '', array_merge( array( 'cellData' => $cellData,
                                                                                             'showLines' => $showLines ),
                                                                                      $params ) ) );
    }

    /**
     * Function for extracting function name and parameters from text.
     *
     * \param text
     * \param offset
     * \param function name (reference)
     * \param parameters array (reference)
     *
     * \return end offset of function
     */
    function extractFunction( &$text, $offSet, &$functionName, &$parameters, $type='ezCall' )
    {
        $offSet++;
        $offSet += strlen( $type.':' );
        $funcEnd = strpos( $text, ':', $offSet );
        if ( $funcEnd === false || strpos( $text, '>', $offSet ) < $funcEnd )
        {
            $funcEnd = strpos( $text, '>', $offSet );
        }
        $functionName = substr( $text, $offSet, $funcEnd - $offSet );

        return $this->extractParameters( $text, $funcEnd, $parameters );
    }

    /**
     * Function for extracting parameters from : separated key:value list callback functions
     *
     * \param text
     * \param offset
     * \param parameters array (reference)
     *
     * \return end offset of function
     */
    function extractParameters( &$text, $offSet, &$parameters, $skipFirstChar=false )
    {
        $endOffset = strpos( $text, '>', $offSet );
        if ( $endOffset === false )
        {
            $endOffset = strlen( $text );
        }

        if ( $skipFirstChar === false )
            $offSet++;
        while ( $offSet < $endOffset )
        {
            $nameEnd = strpos( $text, ':', $offSet );
            $valueEnd = strpos( $text, ':', $nameEnd+1 );
            if ( $valueEnd > $endOffset || $valueEnd === false )
            {
                $valueEnd = $endOffset;
            }
            $paramName = substr( $text, $offSet, $nameEnd-$offSet);
            ++$nameEnd;
            $paramValue = substr( $text, $nameEnd, $valueEnd-$nameEnd );
            $parameters[$paramName] = $paramValue;
            $offSet = ++$valueEnd;
        }

        return $endOffset;
    }

    /**
      Loop through all document specification settings and print specified text

      \return new Y offset
     */
    function outputDocSpecification()
    {
        foreach( array_keys( $this->DocSpecification ) as $key )
        {
            $outputElement =& $this->DocSpecification[$key];

            $documentSpec =& $outputElement['docSpec'];

            if ( isset( $documentSpec['fontName'] ) )
            {
                $this->selectFont( $documentSpec['fontName'] );
            }

            if ( isset( $documentSpec['fontSize'] ) )
            {
                $size = $documentSpec['fontSize'];
            }
            else
            {
                $size = $this->fontSize();
            }

            if ( isset( $documentSpec['cmyk'] ) )
            {
                $this->setColor( $documentSpec['cmyk'] );
            }

            if ( isset( $outputElement['isFunction'] ) && $outputElement['isFunction'] === true )
            {
                $return = call_user_func_array( array( &$this, $outputElement['functionName'] ), $outputElement['parameters'] );
            }
            else
            {
                $return = Cezpdf::ezText( $outputElement['text'],
                                          $size,
                                          array( 'justification' => $documentSpec['justification'] ) );
            }
        }
        return $return;
    }

    /*!
     Callback function for adding text frame.
     */
    function callTextFrame( $params, $text )
    {
        $this->addDocSpecFunction( 'insertTextFrame', array( $params, $text ) );
    }

    /*!
     Callback function for adding text frame.
     */
    function insertTextFrame( $params, $text )
    {
        $prevColor = $this->currentColour;
        $prevFontSize = $this->fontSize();
        $prevFont = $this->currentFont();

        if ( isset( $params['fontSize'] ) )
        {
            $this->setFontSize( $params['fontSize'] );
        }

        if ( isset( $params['fontName'] ) )
        {
            $this->selectFont( $params['fontName'] );
        }

        $cmykKeys = array( 'c', 'm', 'y', 'k' );
        if ( isset( $params ['frameCMYK'] ) )
        {
            $params['frameCMYK'] = explode( ',', $params['frameCMYK'] );
            foreach ( $cmykKeys as $oldKey => $newKey )
            {
                $params['frameCMYK'][$newKey] = $params['frameCMYK'][$oldKey];
                unset( $params['frameCMYK'][$oldKey] );
            }
        }
        if ( isset( $params['textCMYK'] ) )
        {
            $params['textCMYK'] = explode( ',', $params['textCMYK'] );
            foreach ( $cmykKeys as $oldKey => $newKey )
            {
                $params['textCMYK'][$newKey] = $params['textCMYK'][$oldKey];
                unset( $params['textCMYK'][$oldKey] );
            }
        }

        $padding = 0;
        if ( isset( $params['padding'] ) )
        {
            $padding = $params['padding'];
        }

        $leftPadding = $padding;
        $rightPadding = $padding;
        $topPadding = $padding;
        $bottomPadding = $padding;

        if ( isset( $params['leftPadding'] ) )
        {
            $leftPadding = $params['leftPadding'];
        }
        if ( isset( $params['rightPadding'] ) )
        {
            $rightPadding = $params['rightPadding'];
        }
        if ( isset( $params['topPadding'] ) )
        {
            $topPadding = $params['topPadding'];
        }
        if ( isset( $params['bottomPadding'] ) )
        {
            $bottomPadding = $params['bottomPadding'];
        }

        $yOffset = $this->yOffset();
        $xOffset = $this->xOffset();

        $fontHeight = $this->getFontHeight( $this->ez['fontSize'] );
        $fontDecender = $this->getFontDecender( $this->ez['fontSize'] );
        $textWidth = $this->getTextWidth( $this->ez['fontSize'], $text );

        $totalHeight = $fontHeight + $topPadding;
        $totalWidth = $textWidth + $leftPadding + $rightPadding;

        if ( $rightPadding == -1 )
        {
            $totalWidth = $leftPadding + $this->ez['pageWidth'] - $xOffset + 10;
        }

        if ( isset( $params['frameCMYK'] ) )
        {
            $this->setColor( $params['frameCMYK'] );
        }
        $this->filledRectangle( $xOffset - $leftPadding,
                                $yOffset - $bottomPadding,
                                $totalWidth,
                                $totalHeight );

        if ( isset( $params['roundEnds'] ) )
        {
            if ( $rightPadding != -1 )
            {
                $this->filledEllipse( $xOffset + $textWidth + $rightPadding, $yOffset - $bottomPadding + ( $totalHeight / 2 ), $totalHeight / 2 );
            }
            if ( $leftPadding != -1 )
            {
                $this->filledEllipse( $xOffset - $leftPadding, $yOffset - $bottomPadding + ( $totalHeight / 2 ), $totalHeight / 2 );
            }
        }

        if ( isset( $params['textCMYK'] ) )
        {
            $this->setColor( $params['textCMYK'] );
        }
        else
        {
            $this->setColor( $prevColor );
        }
        $this->addText( $xOffset, $yOffset, $this->fontSize(), $text );

        $this->setColor( $prevColor );
        $this->setFontSize( $prevFontSize );
        $this->selectFont( $prevFont );
    }

    /**
     * Callback function for adding text
     */
    function callText( $params )
    {
        $options = array();

        if ( isset( $params['font'] ) )
        {
            $options['fontName'] = 'lib/ezpdf/classes/fonts/'. $params['font'];
        }

        if ( isset( $params['size'] ) )
        {
            $options['fontSize'] = $params['size'];
        }

        if ( isset( $params['justification'] ) )
        {
            $options['justification'] = $params['justification'];
        }

        if ( isset( $params['cmyk'] ) )
        {
            $keyArray = array ( 'c', 'm', 'y', 'k' );
            $options['cmyk'] = array();
            $params['cmyk'] = explode( ',', $params['cmyk'] );
            foreach ( array_keys( $params['cmyk'] ) as $key )
            {
                $options['cmyk'][$keyArray[$key]] = $params['cmyk'][$key];
            }
        }

        $this->addToPreStack( $options );

        return '';
    }

    /**
     * Initialize PreStack
     */
    function initPreStack()
    {
        include_once( 'lib/ezutils/classes/ezmath.php' );
        $this->PreStack[] = array( 'justification' => $this->justification(),
                                   'fontSize' => $this->fontSize(),
                                   'fontName' => 'lib/ezpdf/classes/fonts/Helvetica',
                                   'cmyk' => eZMath::rgbToCMYK2( 0, 0, 0 ) );
    }

    /**
     * Function for adding text to doc specification
     *
     * param - text to add
     */
    function addDocSpecification( $text )
    {
        $docSpec = array_pop( $this->PreStack );
        $this->DocSpecification[] = array ( 'docSpec' => $docSpec,
                                            'text' => $text );
        $this->PreStack[] = $docSpec;
    }

    /**
     * Function for adding function to doc specification
     *
     * param - text to add
     */
    function addDocSpecFunction( $functionName, $parameters )
    {
        $docSpec = array_pop( $this->PreStack );
        $this->DocSpecification[] = array ( 'docSpec' => $docSpec,
                                            'isFunction' => true,
                                            'functionName' => $functionName,
                                            'parameters' => $parameters );
        $this->PreStack[] = $docSpec;
    }


    /**
     * function for adding font specification to PreStack array
     *
     * Possible $options setting:
     * - justification
     * - fontSize
     * - fontName
     */
    function addToPreStack( $options=array() )
    {
        $currentElement = array();

        $prevElement = array_pop( $this->PreStack );

        if ( isset( $options['justification'] ) )
        {
            $currentElement['justification'] = $options['justification'];
        }
        else
        {
            $currentElement['justification'] = $prevElement['justification'];
        }

        if ( isset( $options['fontSize'] ) )
        {
            $currentElement['fontSize'] = $options['fontSize'];
        }
        else
        {
            $currentElement['fontSize'] = $prevElement['fontSize'];
        }

        if ( isset( $options['fontName'] ) )
        {
            $currentElement['fontName'] = $options['fontName'];
        }
        else
        {
            $currentElement['fontName'] = $prevElement['fontName'];
        }

        if ( isset( $options['cmyk'] ) )
        {
            $currentElement['cmyk'] = $options['cmyk'];
        }
        else
        {
            $currentElement['cmyk'] = $prevElement['cmyk'];
        }

        $this->PreStack[] = $prevElement;
        $this->PreStack[] = $currentElement;
    }

    /*!
      Draw line related to a frame.
    */
    function callFrameLine( $info )
    {
        $parameters = array();
        $this->extractParameters( $info['p'], 0, $parameters, true );

        $location = $parameters['location'];
        $yOffset = $parameters['margin'];
        if ( $location == 'header' )
        {
            $yOffset = $this->ez['pageHeight'] - $parameters['margin'];
        }

        $rightMargin = $this->rightMargin();
        if ( isset( $parameters['rightMargin'] ) )
        {
            $rightMargin = $parameters['rightMargin'];
        }

        $leftMargin = $this->leftMargin();
        if ( isset( $parameters['leftMargin'] ) )
        {
            $leftMargin = $parameters['leftMargin'];
        }

        foreach ( $this->ezPages as $pageNum => $pageID )
        {
            if( $pageNum < $parameters['pageOffset'] )
                continue;

            if ( $parameters['page'] == 'odd' &&
                 $pageNum % 2 == 0 )
                continue;

            if ( $parameters['page'] == 'even' &&
                 $pageNum % 2 == 1 )
                continue;

            $this->reopenObject( $pageID );
            $this->line( $leftMargin, $yOffset, $this->ez['pageWidth'] - $rightMargin, $yOffset );
            $this->closeObject();
        }
    }

    /*!
     Start page counter in PDF document

     \param counter identifier
    */
    function callStartPageCounter( $info )
    {
        $params = array();

        $this->extractParameters( $info['p'], 0, $params, true );

        $identifier = 'main';
        if ( isset( $params['identifier'] ) )
        {
            $identifier = $params['identifier'];
        }

        if ( isset( $params['start'] ) )
        {
            $this->PageCounter[$identifier] = array();
            $this->PageCounter[$identifier]['start'] = $this->ezGetCurrentPageNumber();
        }
        if ( isset( $params['stop'] ) )
        {
            $this->PageCounter[$identifier]['stop'] = $this->ezGetCurrentPageNumber();
        }
    }

    /*!
     \reimp

     \param real page number
     \param pagecounter identifier
    */
    function ezWhatPageNumber( $pageNum, $identifier = false )
    {
        if ( $identifier === false )
        {
            foreach ( array_keys( $this->PageCounter ) as $identifier )
            {
                if ( isset( $this->PageCounter[$identifier]['start'] ) &&
                     $this->PageCounter[$identifier]['start'] <= $pageNum &&
                     ( !isset( $this->PageCounter[$identifier]['stop'] ) || $this->PageCounter[$identifier]['stop'] >= $pageNum ) )
                    return $pageNum - $this->PageCounter[$identifier]['start'] + 1;
            }
        }
        else
            return $pageNum - $this->PageCounter[$identifier]['start'] + 1;
    }

    /* --- Private --- */
    /*!
      \private

      Get header label of content on specified page and specified level

      \param current page
      \param level
    */
    function headerLabel( $page, $level )
    {
        $headerLabel = '';
        foreach ( array_keys( $this->TOC ) as $key )
        {
            $header = $this->TOC[$key];
            if ( $header['pageNumber'] > $page )
                return $headerLabel;

            if ( $header['level'] == $level )
            {
                $headerLabel = $header['label'];
            }
            else if ( $header['level'] < $level )
            {
                $headerLabel = '';
            }
        }

        return $headerLabel;
    }

    /*!
      \private

      Get header label of content on specified page and specified level

      \param current page
      \param level
    */
    function headerIndex( $page, $level )
    {
        $headerIndex = 0;
        foreach ( array_keys( $this->TOC ) as $key )
        {
            $header = $this->TOC[$key];
            if ( $header['pageNumber'] > $page )
                return ( $headerIndex != 0 ? $headerIndex : '' );

            if ( $header['level'] == $level )
            {
                $headerIndex++;
            }
            else if ( $header['level'] < $level )
            {
                $headerLabel = 0;
            }
        }

        return ( $headerIndex != 0 ? $headerIndex : '' );
    }

    var $TOC; // Table of content array
    var $KeywordArray; // keyword array
    var $PageCounter;

    var $FrontpageID; // Variable used to store reference to frontpage

    var $ezFrame; // array containing frame definitions

    /* Stack and array used for preprocessing document */
    var $PreStack;
    var $DocSpecification;
}


?>
