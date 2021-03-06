<?php
/*
 * Copyright (c)  2009, Tracmor, LLC
 *
 * This file is part of Tracmor.
 *
 * Tracmor is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tracmor is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tracmor; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Include prepend.inc to load Qcodo
require('../includes/prepend.inc.php');
/* if you DO NOT have "includes/" in your include_path */
QApplication::Authenticate();

class AdminLabelsForm extends QForm
{
    // Header Menu
    protected $ctlHeaderMenu;
    
    // Drop-down select list
    protected $lstLabelTypeControl;
    // Modal dialog for printing labels
    protected $dlgPrintLabels;
    protected $lstLabelStock;
    protected $lstLabelOffset;
    protected $btnPrint;
    protected $btnCancel;
    protected $txtWarning;
    
    // Search Menu
    protected $ctlSearchMenu;
    // Buttons
    protected $btnPrintLabels;
    //protected $blnPrintLabels;
    
    // Array of ObjectIds of checked items
    protected $intObjectIdArray;
    protected $strBarCodeArray;
    protected $intCurrentBarCodeLabel;
    protected $intLabelsPerPage;
    protected $strTablesBufferArray;
	protected $strLabelArray;
    
    protected function Form_Create()
    {
        // Create the Header Menu
        $this->ctlHeaderMenu_Create();
        // Create Label Type
        $this->lstLabelTypeControl_Create();
        // Create Print Labels button
        $this->btnPrintLabels_Create();
        // Create Modal Window for Printing Labels
        $this->dlgPrintLabels_Create();
    }
    
    // Create and Setup the Header Composite Control
    protected function ctlHeaderMenu_Create()
    {
        $this->ctlHeaderMenu = new QHeaderMenu($this);
    }
    
    // Create and Setup the Label Type drop-down select list
    protected function lstLabelTypeControl_Create()
    {
        $this->lstLabelTypeControl = new QListBox($this);
        $this->lstLabelTypeControl->Width = 150;
        $this->lstLabelTypeControl->AddItem(new QListItem('- Select One -', 0));
        $this->lstLabelTypeControl->AddItem(new QListItem('Assets', 1));
        $this->lstLabelTypeControl->AddItem(new QListItem('Inventory', 2));
        $this->lstLabelTypeControl->AddItem(new QListItem('Locations', 3));
        $this->lstLabelTypeControl->AddItem(new QListItem('Users', 4));
        $this->lstLabelTypeControl->AddAction(new QChangeEvent(), new QServerAction('lstLabelTypeControl_Change'));
    }
    
    // Create and Setup the Modal Window for Printing Labels
    protected function dlgPrintLabels_Create()
    {
        $this->dlgPrintLabels = new QDialogBox($this);
        $this->dlgPrintLabels->Text = '';
        
        // Let's setup some basic appearance options
        $this->dlgPrintLabels->Width = '300px';
        $this->dlgPrintLabels->Height = '100px';
        $this->dlgPrintLabels->Overflow = QOverflow::Auto;
        $this->dlgPrintLabels->Padding = '10px';
        $this->dlgPrintLabels->FontSize = '12px';
        //$this->dlgPrintLabels->FontNames = QFontFamily::Georgia;
        $this->dlgPrintLabels->BackColor = '#ffffff';
        // Make sure this Dislog Box is "hidden"
        $this->dlgPrintLabels->Display = false;
        
        /* If you try to make moveable - error "qc.regDB is not a function"
        $this->dlgPrintLabels->Position = QPosition::Absolute;
        $this->dlgPrintLabels->AddControlToMove();
        */
        
        // Add some controls into modal window
        $this->lstLabelStock = new QListBox($this->dlgPrintLabels);
        $this->lstLabelStock->Width = 200;
        $this->lstLabelStock->AddItem(new QListItem('- Select One -', 0));
        $this->lstLabelStock->AddItem(new QListItem('Avery 6571/6577 (5/8" x 3")', 1));
        $this->lstLabelStock->AddItem(new QListItem('Avery 6570/6576 (1-1/4" x 1-3/4")', 2));
        $this->lstLabelStock->AddAction(new QChangeEvent(), new QAjaxAction('lstLabelStock_Change'));
        $this->lstLabelOffset = new QListBox($this->dlgPrintLabels);
        $this->lstLabelOffset->Width = 200;
        $this->lstLabelOffset->AddItem(new QListItem('None', 0, 1));
        $this->btnPrint = new QButton($this->dlgPrintLabels);
        $this->btnPrint->Text = "Print";
        $this->btnPrint->AddAction(new QClickEvent(), new QToggleEnableAction($this->btnPrint));
        $this->btnPrint->AddAction(new QClickEvent(), new QToggleEnableAction($this->lstLabelStock, false));
        $this->btnPrint->AddAction(new QClickEvent(), new QAjaxAction('FERbtnPrint_Click'));
        $this->btnCancel = new QButton($this->dlgPrintLabels);
        $this->btnCancel->Text = "Cancel";
        $this->btnCancel->AddAction(new QClickEvent(), new QAjaxAction('btnCancel_Click'));
        $this->btnCancel->AddAction(new QClickEvent(), new QJavaScriptAction("document.getElementById('warning_loading').innerHTML = '';"));
        $this->txtWarning = new QLabel($this->dlgPrintLabels);
        $this->txtWarning->Text = "Please wait... PDF Generating: 0% Complete";
        $this->txtWarning->Display = false;
        $this->dlgPrintLabels->Template = 'labels_printing_labels.tpl.php';
    }
    
    // Create and Setup the Print Labels Button
    protected function btnPrintLabels_Create()
    {
        $this->btnPrintLabels = new QButton($this);
        $this->btnPrintLabels->Text = 'Print Labels';
        $this->btnPrintLabels->AddAction(new QClickEvent(), new QJavaScriptAction("document.getElementById('warning_loading').innerHTML = 'Please wait... loading.';"));
        $this->btnPrintLabels->AddAction(new QClickEvent(), new QToggleEnableAction($this->btnPrintLabels));
        $this->btnPrintLabels->AddAction(new QClickEvent(), new QAjaxAction('btnPrintLabels_Click'));
        $this->btnPrintLabels->Display = false;
        //$this->blnPrintLabels = false;
    }
    
    // Create and display the search on change Label Type
    protected function lstLabelTypeControl_Change()
    {
        // Create and display search control
        switch ($this->lstLabelTypeControl->SelectedValue) {
            case 1:
                $this->ctlSearchMenu = new QAssetSearchComposite($this, null, true);
                break;
            case 2:
                $this->ctlSearchMenu = new QInventorySearchComposite($this, null, true);
                break;
            case 3:
                $this->ctlSearchMenu = new QLocationSearchComposite($this, null, true);
                break;
            case 4:
                $this->ctlSearchMenu = new QUserSearchComposite($this, null, true);
                break;
            default:
                break;
        }
        // Uncheck all items on change Label Type
        foreach ($this->GetAllControls() as $objControl) {
            if (substr($objControl->ControlId, 0, 11) == 'chkSelected') {
                $objControl->Checked = false;
            }
        }
        // Show/Hide Print Labels button
        if (!$this->ctlSearchMenu) {
            $this->btnPrintLabels->Display = false;
        } else {
            $this->btnPrintLabels->Display = true;
            $this->btnPrintLabels->Enabled = true;
        }
    }
    
    // Print Lables button click action
    protected function btnPrintLabels_Click()
    {
        //if ($this->blnPrintLabels) {
		$this->strLabelArray = array();
        $this->strBarCodeArray = array();
        $this->strTablesBufferArray = array();
        $this->intCurrentBarCodeLabel = 0;
        // Set start value for PDF generation progress bar
        $_SESSION["intGeneratingStatus"] = 0;
        set_time_limit(0);
        $blnError = false;
        // Array[0] - DataGrid Object name; array[1] - Id; array[2] - used for Bar Code Label Generation
        $arrDataGridObjectNameId = $this->ctlSearchMenu->GetDataGridObjectNameId();
        $this->intObjectIdArray = $this->ctlSearchMenu->$arrDataGridObjectNameId[0]->GetSelected($arrDataGridObjectNameId[1]);
        $objCheckedArray = array();
        if (count($this->intObjectIdArray)) {
            // Switch statement for all four entity types
            switch ($this->lstLabelTypeControl->SelectedValue) {
                case 1:
                    // Load an array of Assets by AssetId
                    $objCheckedArray = Asset::QueryArray(QQ::In(QQN::Asset()->AssetId, $this->intObjectIdArray));
                    break;
                case 2:
                    // Load an array of Inventories by InventoryModelId
                    $objCheckedArray = InventoryModel::QueryArray(QQ::In(QQN::InventoryModel()->InventoryModelId, $this->intObjectIdArray));
                    break;
                case 3:
                    // Load an array of Locations by LocationId
                    $objCheckedArray = Location::QueryArray(QQ::In(QQN::Location()->LocationId, $this->intObjectIdArray));
                    break;
                case 4:
                    $objCheckedArray = UserAccount::QueryArray(QQ::In(QQN::UserAccount()->UserAccountId, $this->intObjectIdArray));
                    break;
                default:
                    $this->btnPrintLabels->Warning = "Please select Label Type.<br/>";
                    $this->intObjectIdArray = array();
                    $blnError = true;
                    break;
            }
            $objArrayById = array();
            // Create array of objects where the key is Id
            foreach ($objCheckedArray as $objChecked) {
                $objArrayById[$objChecked->$arrDataGridObjectNameId[1]] = $objChecked;
            }
            // Fill the BarCodeArray in the order items sorted in the datagrid
            foreach ($this->intObjectIdArray as $intObjectId) {
                $this->strBarCodeArray[] = $objArrayById[$intObjectId]->$arrDataGridObjectNameId[2];
				//Only Assets are grabbing something other than the ID for text, so must switch.
				// Might add more later, so leaving switch that could be an ifthen.
				switch ($this->lstLabelTypeControl->SelectedValue) {
					case 1:
						// Grab model name for label
						$this->strLabelArray[] = $objArrayById[$intObjectId]->$arrDataGridObjectNameId[3];
						break;
					default:
						$this->strLabelArray[] = $objArrayById[$intObjectId]->$arrDataGridObjectNameId[2];
						break;
				}
            }
        } else {
            $blnError = true;
        }
        
        if (!$blnError) {
            $this->btnPrintLabels->Warning = "";
            $this->lstLabelStock->SelectedValue = 0;
            $this->lstLabelOffset->RemoveAllItems();
            $this->lstLabelOffset->AddItem(new QListItem('None', 0, 1));
            $this->lstLabelStock->Enabled = true;
            $this->lstLabelOffset->Enabled = true;
            $this->dlgPrintLabels->ShowDialogBox();
        } else {
            // If we have no checked items
            $this->btnPrintLabels->Warning .= "You must check at least one item.";
        }
        // Enable Print Labels button
        $this->btnPrintLabels->Enabled = true;
        //$this->blnPrintLabels = false;
        /*}
        else {
        $this->btnPrintLabels->Warning = "Please wait... loading.";
        $this->blnPrintLabels = true;
        QApplication::ExecuteJavaScript("document.getElementById('".$this->btnPrintLabels->ControlId."').click(); document.getElementById('warning_loading').innerHTML = '';");
        }*/
        QApplication::ExecuteJavaScript("document.getElementById('warning_loading').innerHTML = '';");
    }
    
    // Cancel button click action
    protected function btnCancel_Click()
    {
        // Terminate PDF generating
        $_SESSION["intGeneratingStatus"] = -1;
        $this->dlgPrintLabels->HideDialogBox();
        $this->btnPrint->Enabled = true;
        $this->btnPrintLabels->Enabled = true;
        
        /*
        // Uncheck all items but SelectAll checkbox
        foreach ($this->GetAllControls() as $objControl) {
        if (substr($objControl->ControlId, 0, 11) == 'chkSelected') {
        $objControl->Checked = false;
        }
        }
        $arrDataGridObjectNameId = $this->ctlSearchMenu->GetDataGridObjectNameId();
        // Uncheck SelectAll checkbox
        $this->ctlSearchMenu->$arrDataGridObjectNameId[0]->chkSelectAll->Checked = false;
        */
        
        // Delete temporary images
        for ($i = 1; $i <= $this->intCurrentBarCodeLabel; $i++) {
            @unlink(sprintf('%s/%s_%s.png', __TRACMOR_TMP__, $_SESSION['intUserAccountId'], $i));
        }
        // Reset variables
        $this->intCurrentBarCodeLabel = 0;
        $this->strBarCodeArray = array();
        $this->strTablesBufferArray = array();
        $this->txtWarning->Text = "";
        $this->txtWarning->Display = false;
    }
    
    // Create the Label Offset drop-down menu on change Label Stock
    protected function lstLabelStock_Change()
    {
        if ($this->lstLabelStock->SelectedValue) {
            $intLabelOffsetCount = 0;
            $this->lstLabelStock->Warning = "";
            $this->lstLabelOffset->RemoveAllItems();
            $this->lstLabelOffset->AddItem(new QListItem('None', 0, 1));
            switch ($this->lstLabelStock->SelectedValue) {
                case 1:
                    // Labels per page for Avery 6577 (5/8" x 3")
                    $this->intLabelsPerPage = 32; // 16 lines * 2 columns
                    break;
                case 2:
                    // Labels per page for Avery 6576 (1-1/4" x 1-3/4")
                    $this->intLabelsPerPage = 32; // 8 lines * 4 columns
                    break;
                default:
                    throw new QCallerException('Label Stock Not Provided');
                    break;
            }
            
            for ($i = 1; $i < $this->intLabelsPerPage; $i++) {
                $this->lstLabelOffset->AddItem(new QListItem($i, $i));
            }
            $this->btnPrint->Enabled = true;
        } else {
            $this->lstLabelStock->Warning = "Please select one";
            $this->lstLabelStock->Enabled = true;
        }
    }
    
    // Create and Setup the table per each page for Bar Code Label Generation
    protected function CreateTableByBarCodeArray()
    {
        $strTable = "<table height=\"100%\" border=\"0\" style=\"text-align:center\">";
        $intBarCodeArrayCount = count($this->strBarCodeArray);
        switch ($this->lstLabelStock->SelectedValue) {
            case 1:
                // Labels per row for Avery 6577 (5/8" x 3")
                $intNumberInTableRow = 2; // Cells per row
                $intImageHeight = 41; // Bar Code Image Height
                $intCellWidth = 258; // Cell Width
                $intBlankSpace = 10; // Blank Cell Width
                break;
            case 2:
                // Labels per row for Avery 6576 (1-1/4" x 1-3/4")
                $intNumberInTableRow = 4; // Cells per row
                $intImageHeight = 60; // Bar Code Image Height
                $intCellWidth = 130; // Cell Width
                $intBlankSpace = 10; // Blank Cell Width
                break;
            default:
                throw new QCallerException('Label Stock Not Provided');
                break;
        }
        
        $i = 0;
        while ($i < $this->intLabelsPerPage) {
            $strTable .= "<tr>";
            $j = 0;
            $arrTD = array();
            while ($j < $intNumberInTableRow) {
                // If Label Offset set
                if ($i < $this->lstLabelOffset->SelectedValue && $this->intCurrentBarCodeLabel == 0) {
                    $arrTD[] = sprintf("<td width=\"%spx\" style=\"text-align:center\"><img src=\"../includes/php/tcpdf/images/_blank.png\" height=\"%s\" /></td>", $intCellWidth, $intImageHeight);
                } elseif ($this->intCurrentBarCodeLabel < $intBarCodeArrayCount) {
                    $arrTD[] = sprintf("<td width=\"%spx\" style=\"text-align:center\"><img src=\"%s/%s_%s.png\" height=\"%s\" border=\"0\" align=\"center\" /></td>", $intCellWidth, __TRACMOR_TMP__, $_SESSION['intUserAccountId'], $this->intCurrentBarCodeLabel + 1, $intImageHeight);
                    $image = QApplication::DrawBarcode($this->strBarCodeArray[$this->intCurrentBarCodeLabel++]);
                    
                    if ($this->lstLabelStock->SelectedValue != 1) {
                        // Get image width
                        $intImageSx = imagesx($image);
                        // Get image height
                        $intImageSy = imagesy($image);
                        // Scale
                        $intRatio = $intImageHeight / $intImageSy;
                        // Create new image
                        $new_image = imagecreatetruecolor($intCellWidth - 2, $intImageHeight);
                        // Background Color
                        $background = imagecolorallocate($new_image, 255, 255, 255); // White
                        //$background = imagecolorallocate($new_image, 0, 0, 0); // Black
                        imagefilledrectangle($new_image, 0, 0, $intCellWidth - 2, $intImageHeight, $background);
                        // If the width of scalable image less then the width of the cell
                        if (($intImageSx * $intRatio) < ($intCellWidth - 2)) {
                            // Add some blank to the right and left
                            imagecopyresampled($new_image, $image, ceil(($intCellWidth - $intImageSx * $intRatio) / 2), 0, 0, 0, ceil($intImageSx * $intRatio), $intImageHeight, $intImageSx, $intImageSy);
                        } else {
                            $intRatio = ($intCellWidth - 2) / $intImageSx;
                            // Add some blank to the top and bottom
                            imagecopyresampled($new_image, $image, 0, ceil(($intImageHeight - $intImageSy * $intRatio) / 2), 0, 0, $intCellWidth - 2, ceil($intImageSy * $intRatio), $intImageSx, $intImageSy);
                        }
                        ImagePNG($new_image, sprintf("%s/%s_%s.png", __TRACMOR_TMP__, $_SESSION['intUserAccountId'], $this->intCurrentBarCodeLabel));
                        imagedestroy($new_image);
                    } else {
                        ImagePNG($image, sprintf("%s/%s_%s.png", __TRACMOR_TMP__, $_SESSION['intUserAccountId'], $this->intCurrentBarCodeLabel));
                    }
                    imagedestroy($image);
                } else {
                    if (!isset($arrImageSize)) {
                        $arrImageSize = getimagesize(sprintf("%s/%s_%s.png", __TRACMOR_TMP__, $_SESSION['intUserAccountId'], $this->intCurrentBarCodeLabel));
                        $arrImageSize[0] = ceil($arrImageSize[0] * ($intImageHeight / $arrImageSize[1]));
                    }
                    $arrTD[] = sprintf("<td width=\"%spx\" style=\"text-align:center\"><img src=\"../includes/php/tcpdf/images/_blank.png\" width=\"%s\" height=\"%s\" /></td>", $intCellWidth, $arrImageSize[0], $intImageHeight);
                }
                $j++;
                $i++;
            }
            $strTable .= implode(sprintf("<td width=\"%spx\" style=\"text-align:center\"></td>", $intBlankSpace), $arrTD) . "</tr>";
        }
        
        $strTable .= "</table>";
        
        // If the user clicked Cancel button or clicked outside of the modal dialog
        if ($_SESSION["intGeneratingStatus"] != -1 || !($this->dlgPrintLabels->Visible && $this->dlgPrintLabels->Display)) {
            // xx% Complete
            $_SESSION["intGeneratingStatus"] = ceil($this->intCurrentBarCodeLabel / $intBarCodeArrayCount * 100);
            return $strTable;
        } else
            return;
    }
    
    protected function FERCreateTableByBarCodeArray()
    {
        $strTable = '
	<style>
    .label{
        width: 167.075px;
        height: 120.205px;
        padding: .125in .3in 0;
        margin-right: 18.9px;

        float: left;

        text-align: center;
        overflow: hidden;

        outline: 1px dotted;
        }
    .page-break  {
        clear: left;
        display:block;
        page-break-after:always;
        }
    </style>
';
        $intBarCodeArrayCount = count($this->strBarCodeArray);
        // Labels per row for Avery 6576 (1-1/4" x 1-3/4")
        $intNumberInTableRow = 4; // Cells per row
        $intImageHeight = 60; // Bar Code Image Height
        $intCellWidth = 130; // Cell Width
        $intBlankSpace = 10; // Blank Cell Width
        
        $i = 0;
        while ($i < $this->intLabelsPerPage) {
            $j = 0;
            $arrTD = array();
            while ($j < $intNumberInTableRow) {
                // If Label Offset set
                if ($i < $this->lstLabelOffset->SelectedValue && $this->intCurrentBarCodeLabel == 0) {
                    $strTable .= "<div class=\"label\"></div>";
                }
                if ($this->intCurrentBarCodeLabel < $intBarCodeArrayCount) {
                    $strTable .= sprintf("<div class=\"label\"><img src=\"%s/%s_%s.png\" /><br>GF Consulting</div>\n", __TRACMOR_TMP__, $_SESSION['intUserAccountId'], $this->intCurrentBarCodeLabel + 1);
                    $image = QApplication::DrawBarcode($this->strBarCodeArray[$this->intCurrentBarCodeLabel++]);
                    ImagePNG($image, sprintf("%s/%s_%s.png", __TRACMOR_TMP__, $_SESSION['intUserAccountId'], $this->intCurrentBarCodeLabel));
                    imagedestroy($image);
                } else {
                    $strTable .= "<div class=\"label\"></div>";
                }
                $j++;
                $i++;
            }
        }
        
        // If the user clicked Cancel button or clicked outside of the modal dialog
        if ($_SESSION["intGeneratingStatus"] != -1 || !($this->dlgPrintLabels->Visible && $this->dlgPrintLabels->Display)) {
            // xx% Complete
            $_SESSION["intGeneratingStatus"] = ceil($this->intCurrentBarCodeLabel / $intBarCodeArrayCount * 100);
            return $strTable;
        } else
            return;
    }
    
    // Print button click action
    protected function btnPrint_Click()
    {
        if ($this->lstLabelStock->SelectedValue) {
            
            $this->lstLabelStock->Warning = "";
            
            set_time_limit(0);
            // If the user clicked Cancel button
            if ($_SESSION["intGeneratingStatus"] != -1) {
                // If the user clicked outside of the modal dialog
                if ($this->dlgPrintLabels->Visible && $this->dlgPrintLabels->Display) {
                    if ($this->intCurrentBarCodeLabel < count($this->strBarCodeArray)) {
                        array_push($this->strTablesBufferArray, $this->CreateTableByBarCodeArray());
                        $this->txtWarning->Text = "Please wait... PDF Generating: " . $_SESSION["intGeneratingStatus"] . "% Complete";
                        $this->txtWarning->Display = true;
                        $this->btnPrint->Enabled = true;
                        QApplication::ExecuteJavaScript("document.getElementById('" . $this->btnPrint->ControlId . "').click();");
                    } else {
                        include_once('../includes/php/tcpdf/config/lang/eng.php');
                        include_once('../includes/php/tcpdf/tcpdf.php');
                        
                        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true);
                        // Set document information
                        $pdf->SetCreator("Tracmor");
                        $pdf->SetAuthor("Tracmor");
                        $pdf->SetTitle("Bar Codes");
                        
                        // Set PDF viewer preferences
                        $arrPreferences = array(
                            // Prevent Acrobat from scaling the page
                            'PrintScaling' => 'None' // None, AppDefault 
                        );
                        $pdf->setViewerPreferences($arrPreferences);
                        
                        // Disable header and footer
                        $pdf->setPrintHeader(false);
                        $pdf->setPrintFooter(false);
                        
                        // Disable auto page breaks
                        $pdf->SetAutoPageBreak(false);
                        
                        // Set some language-dependent strings
                        $pdf->setLanguageArray($l);
                        
                        // Set the color used for all drawing operations (lines, rectangles and cell borders).
                        $pdf->SetDrawColor(255); // white
                        // Set Cell Padding
                        $pdf->SetCellPadding(0);
                        // Set Cell Spacing
                        $pdf->SetLineWidth(0);
                        
                        // Initialize document
                        //$pdf->AliasNbPages();
                        
                        switch ($this->lstLabelStock->SelectedValue) {
                            case 1:
                                // Labels per row for Avery 6577 (5/8" x 3")
                                $pdf->SetFontSize(3);
                                $pdf->setCellHeightRatio(2.3); // was 1.93
                                // Set margins
                                $pdf->SetMargins(12, 12, 12);
                                break;
                            case 2:
                                // Labels per row for Avery 6576 (1-1/4" x 1-3/4")
                                $pdf->SetFontSize(3);
                                $pdf->setCellHeightRatio(11.0); // was 10.2
                                // Set margins
                                $pdf->SetMargins(10, 16, 10);
                                break;
                            default:
                                throw new QCallerException('Label Stock Not Provided');
                                break;
                        }
                        
                        foreach ($this->strTablesBufferArray as $strTableBuffer) {
                            // add a page
                            $pdf->AddPage();
                            // output the HTML content
                            $pdf->writeHTML($strTableBuffer);
                        }
                        
                        // Close and save PDF document
                        $pdf->Output(sprintf('%s/%s_BarCodes.pdf', __TRACMOR_TMP__, $_SESSION['intUserAccountId']), "F");
                        // Cleaning up
                        $this->btnCancel_Click();
                        
                        // Uncheck all items but SelectAll checkbox
                        foreach ($this->GetAllControls() as $objControl) {
                            if (substr($objControl->ControlId, 0, 11) == 'chkSelected') {
                                $objControl->Checked = false;
                            }
                        }
                        $arrDataGridObjectNameId = $this->ctlSearchMenu->GetDataGridObjectNameId();
                        // Uncheck SelectAll checkbox
                        $this->ctlSearchMenu->$arrDataGridObjectNameId[0]->chkSelectAll->Checked = false;
                        
                        // Open generated PDF in new window
                        $strTempPath = substr(__TRACMOR_TMP__, strlen(__DOCROOT__ . __SUBDIRECTORY__));
                        QApplication::ExecuteJavaScript(sprintf("window.open('..%s/%s_BarCodes.pdf','Barcodes','resizeable,menubar=1,scrollbar=1,left=0,top=0,width=800,height=600');", $strTempPath, $_SESSION['intUserAccountId']));
                    }
                } else {
                    // Cleaning up
                    $this->btnCancel_Click();
                }
            }
        } else {
            $this->lstLabelStock->Warning = "Please select one";
            $this->lstLabelStock->Enabled = true;
        }
    }
    
    // Print button click action
    protected function FERbtnPrint_Click() 
    {
		$sprintfOwnerTxt = "GF Consulting Asset: %s";
		
		// Actual Measures for Avery 6576 (1-1/4" x 1-3/4")
		// Using default TCPDF units of mm
		$intLabelsPerRow = 4; // Cells per row
		$intBCHeight = 19.6; // Bar Code Image Height
		$intBCWidth = 44.3;
		$intCellHeight = 31.6; // Cell Height (should be 31.75)
		$intCellWidth = 44.3; // Cell Width (should be 44.45)
		$intGapWidth = 4.9; // Spacer Cell Width (more like 5, but shrunk to fit 8x11" or 215.9x279.4mm)
		$intPageTMargin = 13.3 - 1.2;	// Actual around 14.5, so tweak 
		$intPageBMargin = 13.3;
		$intPageLMargin = 12;
		$intPageRMargin = 2;
		$intOwnerTxtHeight = 6;
		$intDescHeight = 6;
		$intPadding = 2;
        
		if ($this->lstLabelStock->SelectedValue) {
            
            $this->lstLabelStock->Warning = "";
            
            set_time_limit(0);
            // If the user clicked Cancel button
            if ($_SESSION["intGeneratingStatus"] != -1) {
                // If the user clicked outside of the modal dialog
                if ($this->dlgPrintLabels->Visible && $this->dlgPrintLabels->Display) {
					$intBarCodeArrayCount = count($this->strBarCodeArray);
					$this->txtWarning->Text = "Please wait... PDF Generating: " . $_SESSION["intGeneratingStatus"] . "% Complete";
					$this->txtWarning->Display = true;
					$this->btnPrint->Enabled = true;
					include_once('../includes/php/tcpdf/config/lang/eng.php');
					include_once('../includes/php/tcpdf/tcpdf.php');
				   
					$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true);
					// Set document information
					$pdf->SetCreator("FerTracmor");
					$pdf->SetAuthor("fer");
					$pdf->SetTitle("Avery 6570 Qcodes");
					
					// Set PDF viewer preferences
					$arrPreferences = array(
						// Prevent Acrobat from scaling the page
						'PrintScaling' => 'None' // None, AppDefault 
					);
					$pdf->setViewerPreferences($arrPreferences);
					
					// Disable header and footer
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
					
					// Allow auto page breaks
					$pdf->SetAutoPageBreak(true, $intPageBMargin);
					
					// Set some language-dependent strings
					if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
						require_once(dirname(__FILE__).'/lang/eng.php');
						$pdf->setLanguageArray($l);
					}
					
					// Set the color used for all drawing operations (lines, rectangles and cell borders).
					//$pdf->SetDrawColor(255); // white
					$pdf->SetCellPadding($intPadding); // Set Cell Padding (for safety)                    
					$pdf->SetLineWidth(0); // For borders (testing)
					$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
					//$pdf->SetFontSize(3);
					//$pdf->setCellHeightRatio(11.0); // was 10.2
					$pdf->SetMargins($intPageLMargin,$intPageTMargin,$intPageRMargin,true);
					
				
					// Prime style with defaults, text will change per item
					$barcodeStyle = array(
						'fgcolor' => array(0,0,0),
						'position' => '',
						//'align' => '',
						'padding' => 0,
						//'fitwidth' => true,
						'border' => false,
						//'text' => true,
						//'label' => 'CUSTOM LABEL',
						//'font' => 'helvetica',
						//'fontsize' => 8,
						//'stretchtext' => 4,
						//'cellfitalign' => true
					);
					
					while ($this->intCurrentBarCodeLabel < $intBarCodeArrayCount) {
						$pdf->AddPage();
						$i = 0;
						while ($i < $this->intLabelsPerPage) {
							$j = 0;
							while ($j < $intLabelsPerRow) {
								// If Label Offset set
								if ($i < $this->lstLabelOffset->SelectedValue && $this->intCurrentBarCodeLabel == 0) {
									//Empty Cell
									$pdf->Cell($intCellWidth,$intCellHeight);
								} else if ($this->intCurrentBarCodeLabel < $intBarCodeArrayCount) {
									// Asset Tag First, flowing to right to capture X,Y for next label start
									$txt = sprintf($sprintfOwnerTxt, $this->strBarCodeArray[$this->intCurrentBarCodeLabel]);
									$pdf->Cell($intCellWidth,$intOwnerTxtHeight,$txt,'TLR',0,'C',false,'',1,true);
									$tmpX=$pdf->GetX();
									$tmpY=$pdf->GetY();
									$pdf->Ln($intOwnerTxtHeight);
									$pdf->SetX($tmpX-$intCellWidth);
									
									//Print QCode
									//$pdf->SetCellPadding(0);
									//$txt = (string)$this->strBarCodeArray[$this->intCurrentBarCodeLabel];									
									$txt = sprintf('http://tracmor.gfconsulting.ca/assets/asset_edit.php?intAssetId=%s',$this->strBarCodeArray[$this->intCurrentBarCodeLabel]);
									//$barcodeStyle['label'] = $this->strLabelArray[$this->intCurrentBarCodeLabel];
									//$pdf->write1DBarcode($txt, 'C39E', '', '', $intBCWidth, $intBCHeight, 0.4, $barcodeStyle, 'T');
									$pdf->write2DBarcode($txt, 'QRCODE,H', '', '', $intBCWidth, $intBCHeight, $barcodeStyle, 'T');
									$pdf->Ln($intBCHeight);
									$pdf->SetX($tmpX-$intBCWidth);
									//$pdf->SetCellPadding($intPadding);
									
									//Print Description
									$txt = (string)$this->strLabelArray[$this->intCurrentBarCodeLabel];
									$pdf->Cell($intCellWidth,$intDescHeight,$txt,'LRB',1,'C',false,'',1,true,'T');
									
									//Reset XY 
									$pdf->SetY($tmpY);
									$pdf->SetX($tmpX);
									
									$this->intCurrentBarCodeLabel++;
								} else {
									$pdf->Cell($intCellWidth,$intCellHeight);
								}
								$j++;
								$i++;
								// Insert spacer between all columns except last.
								if ($j < $intLabelsPerRow) {
									$pdf->Cell($intGapWidth,$intCellHeight);
								}
							}
							$pdf->Ln($intCellHeight);
						}
					}
					
					// move pointer to last page
					$pdf->lastPage();
					// Close and save PDF document
					$pdf->Output(sprintf('%s/%s_BarCodes.pdf', __TRACMOR_TMP__, $_SESSION['intUserAccountId']), "F");
					// Cleaning up
					$this->btnCancel_Click();
					
					// Uncheck all items but SelectAll checkbox
					foreach ($this->GetAllControls() as $objControl) {
						if (substr($objControl->ControlId, 0, 11) == 'chkSelected') {
							$objControl->Checked = false;
						}
					}
					$arrDataGridObjectNameId = $this->ctlSearchMenu->GetDataGridObjectNameId();
					// Uncheck SelectAll checkbox
					$this->ctlSearchMenu->$arrDataGridObjectNameId[0]->chkSelectAll->Checked = false;
					
					// Open generated PDF in new window
					$strTempPath = substr(__TRACMOR_TMP__, strlen(__DOCROOT__ . __SUBDIRECTORY__));
					QApplication::ExecuteJavaScript(sprintf("window.open('..%s/%s_BarCodes.pdf','Barcodes','resizeable,menubar=1,scrollbar=1,left=0,top=0,width=850,height=1100');", $strTempPath, $_SESSION['intUserAccountId']));
                } else {
                    // Cleaning up
                    $this->btnCancel_Click();
                }
            }
        } else {
            $this->lstLabelStock->Warning = "Please select one";
            $this->lstLabelStock->Enabled = true;
        }
    }
}

// Go ahead and run this form object to generate the page
AdminLabelsForm::Run('AdminLabelsForm', 'labels.tpl.php');
?>
