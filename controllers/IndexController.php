<?php
/**
 * The plugin controller for index pages.
 *
 * Technical notes
 * How this works:
 * 1. Select XML file to upload and import options
 * 2. Form accepts and parses XML file, processes it and sends user to next step
 * with a drop down menu with elements that appear to be the document record
 * 3. User selects document record. Variables passed to CsvImportPlus session,
 * user redirected to CsvImportPlus column mapping
 *
 * @copyright Daniel Berthereau, 2012-2016
 * @copyright Scholars' Lab, 2010 [GenericXmlImporter v.1.0]
 * @license http://www.apache.org/licenses/LICENSE-2.0.html
 * @package XmlImport
 */
class XmlImport_IndexController extends Omeka_Controller_AbstractActionController
{
    protected $_pluginConfig = array();

    /**
     * Initialize the controller.
     */
    public function init()
    {
        $this->session = new Zend_Session_Namespace('XmlImport');
    }

    /**
     * Displays main form (step 1).
     */
    public function indexAction()
    {
        if (!XmlImportPlugin::isXsltSupported()) {
            $this->_helper->flashMessenger(__('No xslt processor installed.') . ' ' . __('Check your config.'), 'error');
            return;
        }

        $form = $this->_getMainForm();
        $this->view->form = $form;

        if (!$this->getRequest()->isPost()) {
            return;
        }

        if (!$form->isValid($this->getRequest()->getPost())) {
            $this->_helper->flashMessenger(__('Invalid form input. Please see errors below and try again.'), 'error');
            return;
        }

        $uploadedData = $form->getValues();
        $uploadedData['xml_folder'] = trim($uploadedData['xml_folder']);
        $uploadedData['format_filename'] = $this->_sanitizeName($uploadedData['format_filename']);
        if (empty($uploadedData['format_filename'])) {
            $uploadedData['format_filename'] = '.xml';
        }
        $fileList = array();
        switch ($uploadedData['file_import']) {
            case 'file':
                // If one file is selected, fill the file list with it.
                if ($uploadedData['xml_file'] != '') {
                    if (!$form->xml_file->receive()) {
                        $this->_helper->flashMessenger(__('Error uploading file. Please try again.'), 'error');
                        return;
                    }
                    $csvFilename = pathinfo($form->xml_file->getFileName(), PATHINFO_BASENAME);
                    // Create a file list with one value.
                    $fileList = array($form->xml_file->getFileName() => $csvFilename);
                }
                else {
                    $this->_helper->flashMessenger(__('Error receiving file or no file selected. Verify that there is an XML document.'), 'error');
                    return;
                }
                break;
            case 'folder':
            case 'recursive':
                // Else prepare full files list from the folder.
                if ($uploadedData['xml_folder'] != '') {
                    $fileList = $this->_listRecursiveDirectory(
                        $uploadedData['xml_folder'],
                        $uploadedData['format_filename'],
                        ($uploadedData['file_import'] == 'recursive'));
                    // The csv filename is used only to set the status.
                    $csvFilename = $uploadedData['file_import'] . ' "' . $uploadedData['xml_folder'] . '"';
                    // TODO Upload each file? Currently, they are checked only
                    // with DirectoryIterator.
                }
                else {
                    $this->_helper->flashMessenger(__('Error receiving file or no file selected. Verify that an XML document exists.'), 'error');
                    return;
                }
                break;
            default:
                $this->_helper->flashMessenger(__('Error: you need to choose if you import an xml file or a list of xml files in a folder.'), 'error');
                return;
        }

        if ($fileList === false) {
            $this->_helper->flashMessenger(__('Error accessing directory "%s". Verify that you have rights to access this folder and subfolders.', $uploadedData['xml_folder']), 'error');
            return;
        }
        elseif (empty($fileList)) {
            $this->_helper->flashMessenger(__('Error receiving file or no file selected. Verify that file is an XML document or that the selected directory is not empty.'), 'error');
            return;
        }

        // Check content of each file via a simple xml parsing and hook.
        foreach ($fileList as $filepath => $filename) {
            try {
                $xml_doc = $this->_domXmlLoad($filepath);
            } catch (Exception $e) {
                $this->_helper->flashMessenger($e->getMessage(), 'error');
                return;
            }

            // TODO Check result of the hook.
            // $result = fire_plugin_hook('xml_import_validate_xml_file', $xml_doc);
        }

        // To avoid the memory overload of the Zend session (often 64kb), only
        // the first line is sent (this is a quick process).
        if ($uploadedData['file_import'] != 'file') {
            $value = reset($fileList);
            $fileList = array(key($fileList) => $value);
        }

        // Alright, go to next step.
        try {
            $xmlImportSession = $this->session;
            // TODO Set 1, 2, or more?
            $xmlImportSession->setExpirationHops(3);
            $xmlImportSession->file_import = $uploadedData['file_import'];
            $xmlImportSession->xml_folder = $uploadedData['xml_folder'];
            $xmlImportSession->format_filename = $uploadedData['format_filename'];
            $xmlImportSession->file_list = $fileList;
            $xmlImportSession->csv_filename = $csvFilename;
            $xmlImportSession->action = $uploadedData['action'];
            $xmlImportSession->identifier_field = $this->_elementNameFromPost($uploadedData['identifier_field']);
            $xmlImportSession->item_type_id = $uploadedData['item_type_id'];
            $xmlImportSession->collection_id = $uploadedData['collection_id'];
            $xmlImportSession->public = $uploadedData['records_are_public'];
            $xmlImportSession->featured = $uploadedData['records_are_featured'];
            $xmlImportSession->html_elements = $uploadedData['elements_are_html'];
            $xmlImportSession->contains_extra_data = $uploadedData['contains_extra_data'];
            $xmlImportSession->stylesheet = $uploadedData['stylesheet'];
            $xmlImportSession->stylesheet_intermediate = $uploadedData['stylesheet_intermediate'];
            $xmlImportSession->stylesheet_parameters = $uploadedData['stylesheet_parameters'];

            $delimitersList = self::getDelimitersList();
            $columnDelimiterName = $uploadedData['column_delimiter_name'];
            $xmlImportSession->column_delimiter = isset($delimitersList[$columnDelimiterName])
                ? $delimitersList[$columnDelimiterName]
                : $uploadedData['column_delimiter'];
            $enclosuresList = self::getEnclosuresList();
            $enclosureName = $uploadedData['enclosure_name'];
            $xmlImportSession->enclosure = isset($enclosuresList[$enclosureName])
                ? $enclosuresList[$enclosureName]
                : $uploadedData['enclosure'];
            $elementDelimiterName = $uploadedData['element_delimiter_name'];
            $xmlImportSession->element_delimiter = isset($delimitersList[$elementDelimiterName])
                ? $delimitersList[$elementDelimiterName]
                : $uploadedData['element_delimiter'];
            $tagDelimiterName = $uploadedData['tag_delimiter_name'];
            $xmlImportSession->tag_delimiter = isset($delimitersList[$tagDelimiterName])
                ? $delimitersList[$tagDelimiterName]
                : $uploadedData['tag_delimiter'];
            $fileDelimiterName = $uploadedData['file_delimiter_name'];
            $xmlImportSession->file_delimiter = isset($delimitersList[$fileDelimiterName])
                ? $delimitersList[$fileDelimiterName]
                : $uploadedData['file_delimiter'];

            $this->_helper->redirector->goto('select-element');
        } catch (Exception $e) {
            $this->view->error = $e->getMessage();
        }
    }

    /**
     * Displays second form to choose element (step 1 bis, used only for the
     * generic sheet when there are more than one base record in the xml).
     */
    public function selectElementAction()
    {
        $xmlImportSession = $this->session;
        $view = $this->view;
        $form = $this->_elementForm($xmlImportSession);

        // When tag is not set, display the form to select one.
        if ($form->getValue('tag_name') === null) {
            $this->view->form = $form;
        }
        // Else go directly to next step.
        else {
            $uploadedData = $form->getValues();
            $this->_prepareCsvArguments($uploadedData);
        }
    }

    /**
     * Generates csv file (step 2).
     */
    public function sendAction()
    {
        $xmlImportSession = $this->session;

        // Check if user comes directly here.
        if (!isset($xmlImportSession->file_list)) {
            return;
        }

        $view = $this->view;
        $form = $this->_elementForm($xmlImportSession);
        if ($_POST) {
            if ($form->isValid($this->_request->getPost())) {
                $uploadedData = $form->getValues();
                $this->_prepareCsvArguments($uploadedData);
            }
            else {
                $this->_helper->flashMessenger(__('Error receiving file or no file selected. Verify the XML document.'), 'error');
            }
        }
    }

    /**
     * Helper to prepare array used to generate csv file from a submited form.
     */
    private function _prepareCsvArguments($uploadedData)
    {
        $args = array();
        $args['file_import'] = $uploadedData['file_import'];
        $args['xml_folder'] = $uploadedData['xml_folder'];
        $args['format_filename'] = $uploadedData['format_filename'];
        $args['file_list'] = unserialize($uploadedData['file_list']);
        $args['csv_filename'] = $uploadedData['csv_filename'];
        $args['action'] = $uploadedData['action'];
        $args['identifier_field'] = $uploadedData['identifier_field'];
        $args['item_type_id'] = $uploadedData['item_type_id'];
        $args['collection_id'] = $uploadedData['collection_id'];
        $args['public'] = $uploadedData['records_are_public'];
        $args['featured'] = $uploadedData['records_are_featured'];
        $args['html_elements'] = $uploadedData['elements_are_html'];
        $args['extra_data'] = $uploadedData['contains_extra_data'];
        $args['tag_name'] = $uploadedData['tag_name'];
        $args['column_delimiter'] = $uploadedData['column_delimiter'];
        $args['enclosure'] = $uploadedData['enclosure'];
        $args['element_delimiter'] = $uploadedData['element_delimiter'];
        $args['tag_delimiter'] = $uploadedData['tag_delimiter'];
        $args['file_delimiter'] = $uploadedData['file_delimiter'];
        $args['stylesheet'] = $uploadedData['stylesheet'];
        $args['stylesheet_intermediate'] = $uploadedData['stylesheet_intermediate'];
        $args['stylesheet_parameters'] = $uploadedData['stylesheet_parameters'];

        $this->_generateCsv($args);
    }

    /**
     * Helper to generate csv file.
     */
    private function _generateCsv($args)
    {
        // Get variables from args array passed into detached process.
        $fileImport = $args['file_import'];
        $xmlFolder = $args['xml_folder'];
        $formatFilename = $args['format_filename'];
        if ($fileImport == 'file') {
            $fileList = $args['file_list'];
        }
        else {
            $fileList = $this->_listRecursiveDirectory(
                $xmlFolder,
                $formatFilename,
                ($fileImport == 'recursive'));
        }
        $csvFilename = $args['csv_filename'];
        $action = $args['action'];
        $identifierField = $args['identifier_field'];
        $itemTypeId = $args['item_type_id'];
        $collectionId = $args['collection_id'];
        $recordsArePublic = $args['public'];
        $recordsAreFeatured = $args['featured'];
        $elementsAreHtml = $args['html_elements'];
        $containsExtraData = $args['extra_data'];
        $tagName = $args['tag_name'];
        $columnDelimiter = $args['column_delimiter'];
        $enclosure = $args['enclosure'];
        $elementDelimiter = $args['element_delimiter'];
        $tagDelimiter = $args['tag_delimiter'];
        $fileDelimiter = $args['file_delimiter'];
        $stylesheet = get_option('xml_import_xsl_directory') . DIRECTORY_SEPARATOR . $args['stylesheet'];
        $stylesheetIntermediate = $args['stylesheet_intermediate'];
        $stylesheetParameters = $args['stylesheet_parameters'];

        // Don't use PHP_EOL.
        $endOfLine = "\n";

        $csvFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'omeka_xml_import_'
            . date('Ymd-His') . '_'
            . $this->_sanitizeString($csvFilename) . '.csv';
        $csvFilename = 'Via Xml Import: ' . $csvFilename;

        // If the xslt is an intermediate, prepare the second stylesheet.
        $stylesheetManage = '';
        if ($stylesheetIntermediate) {
            $command = get_option('xml_import_xslt_processor');
            $stylesheetManage = empty($command)
                ? 'advanced.xslt1.xsl'
                : 'advanced.xsl';
            $stylesheetManage = get_option('xml_import_xsl_directory') . DIRECTORY_SEPARATOR . $stylesheetManage;
        }

        // Prepare parameters for the stylesheet.
        $parameters = array(
            'delimiter' => $columnDelimiter,
            'enclosure' => $enclosure,
            'delimiter_element' => $elementDelimiter,
            'delimiter_tag' => $tagDelimiter,
            'delimiter_file' => $fileDelimiter,
            'end_of_line' => $endOfLine,
            'node' => $tagName,
        );
        $parameters['identifier_field'] = $identifierField;
        // Add custom parameters. Allowed types are already checked.
        $parametersAdded = (trim($stylesheetParameters) == '')
            ? array()
            : array_values(array_filter(array_map('trim', explode(PHP_EOL, $stylesheetParameters))));
        $parameterNameErrors = array();
        $parameterValueErrors = array();
        foreach ($parametersAdded as $value) {
            if (strpos($value, '=') !== false) {
                list($paramName, $paramValue) = explode('=', $value);
                $paramName = trim($paramName);
                if ($paramName != '') {
                    $parameters[$paramName] = trim($paramValue);
                }
                // Log the error.
                else {
                    $parameterNameErrors[] = $value;
                }
            }
            // Log the error.
            else {
                $parameterValueErrors[] = $value;
            }
        }

        if ($parameterNameErrors || $parameterValueErrors) {
            if ($parameterNameErrors) {
                $msg = __('An error occurs when parsing xsl specific parameters.');
                $msg .= ' ' . __('Some parameters are badly formatted (no name): [%s].',
                    implode('], [', $parameterNameErrors));
                $this->_helper->flashMessenger($msg, 'error');
            }

            if ($parameterValueErrors) {
                $msg = __('An error occurs when parsing xsl specific parameters.');
                $msg .= ' ' . __('Some parameters are badly formatted (no "=" between name and value): [%s].',
                    implode('], [', $parameterValueErrors));
                $this->_helper->flashMessenger($msg, 'error');
            }
            $this->_helper->redirector->goto('index');
        }

        try {
            // Flag used to keep or remove headers in the first row.
            $flag_first = true;
            // Convert each xml file to csv with the selected stylesheet and
            // parameters. A result can be empty for a file when there are no
            // metadata to import or if the xml file is not a good one.
            foreach ($fileList as $filepath => $filename) {
                // Let headers only for the first file.
                if ($flag_first) {
                    $flag_first = false;
                }
                // Remove first line for all other files.
                else {
                    $parameters['headers'] = 'false';
                }

                $result = $this->_processXslt($filepath, $stylesheet, '', $parameters);
                if ($result === null) {
                    $this->_helper->flashMessenger(__('Error when transforming xml file "%s" with the xsl sheet "%s".', $filepath, $stylesheet), 'error');
                    $this->_helper->redirector->goto('index');
                }

                // If the xslt is an intermediate, process the second conversion.
                if ($stylesheetIntermediate) {
                    $result = $this->_processXslt($result, $stylesheetManage, '', $parameters);
                    if ($result === null) {
                        $this->_helper->flashMessenger(__('Error when transforming xml file "%s" with the xsl sheet "%s" (error while processing with the intermediate sheet).', $filepath, $stylesheet), 'error');
                        $this->_helper->redirector->goto('index');
                    }
                }

                $output = $result;

                // @todo Use Zend/Omeka api.
                $result = $this->_append_file($csvFilePath, $output);
                if ($result === false) {
                    $this->_helper->flashMessenger(__('Error saving data, because the filepath "%s" is not writable.', $filepath), 'error');
                    $this->_helper->redirector->goto('index');
                }
            }

            // Check final resulted file.
            if (filesize($csvFilePath) == 0) {
                $this->_helper->flashMessenger(__('The conversion of the xml file "%s" to csv via the xslt style sheet "%s" gives an empty file. Check your options and your files.', basename($filepath), basename($stylesheet)), 'error');
                $this->_helper->redirector->goto('index');
            }

            // Get the view.
            $view = $this->view;

            // Set up CsvImportPlus validation and column mapping if needed.
            $csvImportFile = new CsvImportPlus_File($csvFilePath, $columnDelimiter, $enclosure);
            $result = $csvImportFile->parse();
            if (!$result) {
                $msg = __('Your CSV file is incorrectly formatted.')
                    . ' ' . $csvImportFile->getErrorString();
                $this->_helper->flashMessenger($msg, 'error');
                $this->_helper->redirector->goto('index');
            }

            // Go directly to the correct view of CsvImportPlus plugin.
            $csvImportSession = new Zend_Session_Namespace('CsvImportPlus');

            // @see CsvImportPlus_IndexController::indexAction().
            $csvImportSession->setExpirationHops(3);
            $csvImportSession->originalFilename = $csvFilename;
            $csvImportSession->filePath = $csvFilePath;
            $csvImportSession->action = $action;
            $csvImportSession->identifierField = $identifierField;
            $csvImportSession->itemTypeId = $itemTypeId;
            $csvImportSession->collectionId = $collectionId;
            $csvImportSession->recordsArePublic = $recordsArePublic;
            $csvImportSession->recordsAreFeatured = $recordsAreFeatured;
            $csvImportSession->elementsAreHtml = $elementsAreHtml;
            $csvImportSession->containsExtraData = $containsExtraData;
            $csvImportSession->columnDelimiter = $columnDelimiter;
            $csvImportSession->enclosure = $enclosure;
            $csvImportSession->columnNames = $csvImportFile->getColumnNames();
            $csvImportSession->columnExamples = $csvImportFile->getColumnExamples();
            // A bug appears in CsvImportPlus when examples contain UTF-8
            // characters like 'ГЧ„чŁ'.
            foreach ($csvImportSession->columnExamples as &$value) {
                $value = iconv('ISO-8859-15', 'UTF-8', @iconv('UTF-8', 'ISO-8859-15' . '//IGNORE', $value));
            }
            $csvImportSession->elementDelimiter = $elementDelimiter;
            $csvImportSession->tagDelimiter = $tagDelimiter;
            $csvImportSession->fileDelimiter = $fileDelimiter;
            $csvImportSession->ownerId = $this->getInvokeArg('bootstrap')->currentuser->id;

            // All is valid, so we save settings.
            set_option('xml_import_stylesheet', $args['stylesheet']);
            set_option('xml_import_stylesheet_intermediate', $args['stylesheet_intermediate']);
            set_option('xml_import_stylesheet_parameters', $args['stylesheet_parameters']);
            set_option('xml_import_format_filename', $args['format_filename']);
            set_option(CsvImportPlus_ColumnMap_IdentifierField::IDENTIFIER_FIELD_OPTION_NAME, $args['identifier_field']);
            set_option(CsvImportPlus_RowIterator::COLUMN_DELIMITER_OPTION_NAME, $args['column_delimiter']);
            set_option(CsvImportPlus_RowIterator::ENCLOSURE_OPTION_NAME, $args['enclosure']);
            set_option(CsvImportPlus_ColumnMap_Element::ELEMENT_DELIMITER_OPTION_NAME, $args['element_delimiter']);
            set_option(CsvImportPlus_ColumnMap_Tag::TAG_DELIMITER_OPTION_NAME, $args['tag_delimiter']);
            set_option(CsvImportPlus_ColumnMap_File::FILE_DELIMITER_OPTION_NAME, $args['file_delimiter']);
            set_option('csv_import_plus_html_elements', $args['html_elements']);
            set_option('csv_import_plus_extra_data', $args['extra_data']);

            if ($csvImportSession->containsExtraData == 'manual') {
                $this->_helper->redirector->goto('map-columns', 'index', 'csv-import-plus');
            }

            $this->_helper->redirector->goto('check-manage-csv', 'index', 'csv-import-plus');

        } catch (Exception $e) {
            $msg = __('Error in your xml file, in your xsl sheet or in your options.')
                . ' ' . __('The xsl sheet should produce a valid csv file with a header and at least one row of metadata.')
                . ' ' . $e->getMessage();
            $this->_helper->flashMessenger($msg, 'error');
            $this->view->error = $msg;
            $this->_helper->redirector->goto('index');
        }
    }

    /**
     * Helper to prepare main form for step 1.
     */
    protected function _getMainForm()
    {
        $csvConfig = $this->_getPluginConfig();
        $form = new XmlImport_Form_Main($csvConfig);
        return $form;
    }

    /**
      * Returns the plugin configuration
      *
      * @return array
      */
    protected function _getPluginConfig()
    {
        if (!$this->_pluginConfig) {
            $config = $this->getInvokeArg('bootstrap')->config->plugins;
            if ($config && isset($config->CsvImportPlus)) {
                $this->_pluginConfig = $config->CsvImportPlus->toArray();
            }
            if (!array_key_exists('fileDestination', $this->_pluginConfig)) {
                $this->_pluginConfig['fileDestination'] =
                    Zend_Registry::get('storage')->getTempDir();
            }
        }
        return $this->_pluginConfig;
    }

    /**
     * Helper to prepare form for step 2.
     *
     * Contains a drop down menu created for tag; other options are hidden
     * inputs.
     */
    private function _elementForm($xmlImportSession)
    {
        // Get first level nodes of first file in order to choose document name.
        $fileList = $xmlImportSession->file_list;
        $stylesheet = $xmlImportSession->stylesheet;
        $stylesheetIntermediate = $xmlImportSession->stylesheet_intermediate;
        $stylesheetParameters = $xmlImportSession->stylesheet_parameters;

        // TODO Add the root element name, that may be needed.
        reset($fileList);
        $filepath = key($fileList);
        try {
            $doc = $this->_domXmlLoad($filepath);
        } catch (Exception $e) {
            $this->_helper->flashMessenger($e->getMessage(), 'error');
            return;
        }

        foreach ($doc->childNodes as $primary) {
            $elementSet = $this->cycleNodes($primary, $elementList = array(), $number = 0);
        }

        require_once "Zend/Form/Element.php";

        $form = new Omeka_Form();
        $form->setAttrib('id', 'xmlimport')
            ->setAction('send')
            ->setMethod('post');

        // Check available record elements inside xml file. This is used only
        // with generic sheets. The tag will not be used in other cases.

        // Check if the node is defined in the list of parameters.
        $parametersAdded = (trim($stylesheetParameters) == '')
            ? array()
            : array_values(array_map('trim', explode(PHP_EOL, $stylesheetParameters)));
        foreach ($parametersAdded as $value) {
            if (strpos($value, '=') !== false) {
                list($paramName, $paramValue) = explode('=', $value);
                $paramName = trim($paramName);
                if ($paramName != '') {
                    $parameters[$paramName] = trim($paramValue);
                }
            }
        }

        // Only one first level tag.
        if (count($elementSet) == 1) {
            reset($elementSet);
            $tagNameElement = new Zend_Form_Element_Hidden('tag_name');
            $tagNameElement->setValue(key($elementSet));
        }
        // Multiple possibilities but not a generic sheet, so take any tag,
        // because it won't be used.
        elseif (substr(basename($stylesheet), 0, 19) !== 'xml_import_generic_') {
            reset($elementSet);
            $tagNameElement = new Zend_Form_Element_Hidden('tag_name');
            $tagNameElement->setValue(key($elementSet));
        }
        // A generic sheet, so check if the node is defined as a specific param.
        elseif (isset($parameters['node'])) {
            reset($elementSet);
            $tagNameElement = new Zend_Form_Element_Hidden('tag_name');
            $tagNameElement->setValue($parameters['node']);
        }
        // Multiple possibilities, so the generic xsl can't choose, so use the
        // second step.
        else {
            $tagNameElement = new Zend_Form_Element_Select('tag_name');
            $tagNameElement
                ->setLabel('Tag Name')
                ->addMultiOptions($elementSet);
        }
        $form->addElement($tagNameElement);

        $fileListElement = new Zend_Form_Element_Hidden('file_list');
        $fileListElement->setValue(serialize($fileList));
        $form->addElement($fileListElement);

        foreach (array(
                'csv_filename' => $xmlImportSession->csv_filename,
                'file_import' => $xmlImportSession->file_import,
                'xml_folder' => $xmlImportSession->xml_folder,
                'format_filename' => $xmlImportSession->format_filename,
                'action' => $xmlImportSession->action,
                'identifier_field' => $xmlImportSession->identifier_field,
                'item_type_id' => $xmlImportSession->item_type_id,
                'collection_id' => $xmlImportSession->collection_id,
                'records_are_public' => $xmlImportSession->public,
                'records_are_featured' => $xmlImportSession->featured,
                'elements_are_html' => $xmlImportSession->html_elements,
                'contains_extra_data' => $xmlImportSession->contains_extra_data,
                'column_delimiter' => $xmlImportSession->column_delimiter,
                'enclosure' => $xmlImportSession->enclosure,
                'element_delimiter' => $xmlImportSession->element_delimiter,
                'tag_delimiter' => $xmlImportSession->tag_delimiter,
                'file_delimiter' => $xmlImportSession->file_delimiter,
                'stylesheet' => $stylesheet,
                'stylesheet_intermediate' => $xmlImportSession->stylesheet_intermediate,
                'stylesheet_parameters' => $xmlImportSession->stylesheet_parameters,
            ) as $name => $value) {
            $element = new Zend_Form_Element_Hidden($name);
            $element->setValue($value);
            $form->addElement($element);
        }

        // Submit button.
        $form->addElement('submit', 'submit');
        $submitElement = $form->getElement('submit');
        $submitElement->setLabel(__('Next'));

        return $form;
    }

    /**
     * Iterate recursively through XML file, extracting out element names that
     * seem to meet requirements for Omeka item record.
     */
    public function cycleNodes($primary, $elementList, $number)
    {
        if ($primary->hasChildNodes()) {
            foreach ($primary->childNodes as $secondary) {
                if ($secondary->hasChildNodes() == false) {
                    $next = $this->cycleNodes($secondary, $elementList, $number);
                }
                else{
                    if ($secondary->nodeName != '#text'
                            && $secondary->nodeName != '#comment'
                            && $secondary->nodeName != (isset($elementList[$number - 1]) ? $elementList[$number - 1] : '')
                        ) {
                        $elementList[$secondary->nodeName] = $secondary->nodeName;
                        $number++;
                    }
                }
            }
        }
        return $elementList;
    }

    /**
     * Iterates recursively through a directory to get all selected filepaths.
     *
     * @param string $directory Directory to check.
     * @param string $extension Extension (with "." if needed).
     * @param boolean $recursive Recursive or not in subfolder.
     *
     * @return associative array of filepath => filename or false if error.
     */
    private function _listRecursiveDirectory($directory, $extension = '', $recursive = true)
    {
        $filenames = array();

        // Get directories and files via http or via file system.
        // Get via http/https.
        if (parse_url($directory, PHP_URL_SCHEME) == 'http' || parse_url($directory, PHP_URL_SCHEME) == 'https') {
            $result = $this->_scandirOverHttp($directory, $extension);
            if (empty($result)) {
                return $filenames;
            }
            $dirs = &$result['dirs'];
            $files = &$result['files'];
        }
        // Get via file system.
        else {
            $dirs = glob($directory . '/*', GLOB_ONLYDIR);
            $files = glob($directory . '/*' . $extension, GLOB_MARK);
            // Remove directories because glob() has no flag to get only files.
            foreach ($files as $key => $file) {
                if (substr($file, -1) == '/') {
                    unset($files[$key]);
                }
            }
        }

        // Recursive call to this function for subdirectories.
        if ($recursive == true) {
            foreach ($dirs as $dir) {
                $subdirectory = $this->_listRecursiveDirectory($dir, $extension);
                if ($subdirectory !== false) {
                    $filenames = array_merge($filenames, $subdirectory);
                }
            }
        }

        // Return filenames in a formatted array.
        foreach ($files as $file) {
            $filenames[$file] = basename($file);
        }

        ksort($filenames);
        return $filenames;
    }

    /**
     * Scan a directory available only via http (web pages).
     *
     * @param $directory string Directory to check.
     * @param string $extension Extension (with "." if needed).
     *
     * @return associative array of directories and filepaths.
     */
    private function _scandirOverHttp($directory, $extension = '')
    {
        $page = @file_get_contents($directory);
        if (empty($page)) {
            return false;
        }

        $dirs = array();
        $files = array();

        // Prepare extension for regex.
        $extension = preg_quote($extension);

        // Add a slash to the url in order to append relative filenames easily.
        if (substr($directory, -1) != '/') {
            $directory .= '/';
        }

        // Get parent directory.
        $parent = dirname($directory) . '/';

        // Get the domain if needed.
        $domain = parse_url($directory);
        $user = isset($domain['user']) ? $domain['user'] : '';
        $pass = isset($domain['pass']) ? $domain['pass'] : '';
        $user = ($user . $pass != '') ? $user . ':' . $pass . '@' : '';
        $port = !empty($domain['port']) ? ':' . $domain['port'] : '';
        $domain = $domain['scheme'] . '://' . $user . $domain['host'] . $port;

        // List all links.
        $matches = array();
        preg_match_all("/(a href\=\")([^\?\"]*)(\")/i", $page, $matches);
        // Remove duplicates.
        $matches = array_combine($matches[2], $matches[2]);

        // Check list of urls.
        foreach ($matches as $match) {
            // Add base url to relative ones.
            $urlScheme = parse_url($match, PHP_URL_SCHEME);
            if ($urlScheme != 'http' && $urlScheme != 'https') {
                // Add only domain to absolute url without domain.
                if (substr($match, 0, 1) == '/') {
                    $match = $domain . $match;
                }
                else {
                    $match = $directory . $match;
                }
            }

            // Remove parent and current directory.
            if ($match == $parent
                    || $match == $directory
                    || ($match . '/') == $parent
                    || ($match . '/') == $directory
                ) {
                // Don't add it.
            }
            // Check if this a directory.
            elseif (substr($match, -1) == '/') {
                $dirs[] = $match;
            }
            elseif (empty($extension)) {
                $files[] = $match;
            }
            // Check the extension.
            elseif (preg_match('/^.+' . $extension . '$/i', $match)) {
                $files[] = $match;
            }
        }

        return array(
            'dirs' => $dirs,
            'files' => $files,
        );
    }

    /**
     * The next functions used to manage xsl transformations are copied from
     * the plugin Archive Folder.
     */

    /**
     * Apply a process (xslt stylesheet) on an input (xml file) and save output.
     *
     * @param string $input Path of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If none, a temp file will
     * be used.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     */
    protected function _processXslt($input, $stylesheet, $output = '', $parameters = array())
    {
        $command = get_option('xml_import_xslt_processor');

        // Default is the internal xslt processor of php.
        return empty($command)
            ? $this->_processXsltViaPhp($input, $stylesheet, $output, $parameters)
            : $this->_processXsltViaExternal($input, $stylesheet, $output, $parameters);
    }

    /**
     * Apply a process (xslt stylesheet) on an input (xml file) and save output.
     *
     * @param string $input Path of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If none, a temp file will
     * be used.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     */
    private function _processXsltViaPhp($input, $stylesheet, $output = '', $parameters = array())
    {
        if (empty($output)) {
            $output = tempnam(sys_get_temp_dir(), 'omk_');
        }

        try {
            $domXml = $this->_domXmlLoad($input);
            $domXsl = $this->_domXmlLoad($stylesheet);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $proc = new XSLTProcessor;
        // Php functions are needed, because php doesn't use XSLT 2.0
        // and because we need to check existence of a file.
        if (get_plugin_ini('XmlImport', 'xml_import_allow_php_in_xsl') == 'true') {
            $proc->registerPHPFunctions();
        }
        $proc->importStyleSheet($domXsl);
        $proc->setParameter('', $parameters);
        $result = $proc->transformToURI($domXml, $output);
        @chmod($output, 0640);

        // There is no message for error with this processor.

        return ($result === false) ? null : $output;
    }

    /**
     * Load a xml or xslt file into a Dom document via file system or http.
     *
     * @param string $filepath Path of xml file on file system or via http.
     * @return DomDocument or throw error message.
     */
    private function _domXmlLoad($filepath)
    {
        $domDocument = new DomDocument;

        // Default import via file system.
        if (parse_url($filepath, PHP_URL_SCHEME) != 'http' && parse_url($filepath, PHP_URL_SCHEME) != 'https') {
            $domDocument->load($filepath);
        }

        // If xml file is over http, need to get it locally to process xslt.
        else {
            $xmlContent = file_get_contents($filepath);
            if ($xmlContent === false) {
                $message = __('Enable to load "%s". Verify that you have rights to access this folder and subfolders.', $filepath);
                throw new Exception($message);
            }
            elseif (empty($xmlContent)) {
                $message = __('The file "%s" is empty. Process is aborted.', $filepath);
                throw new Exception($message);
            }
            $domDocument->loadXML($xmlContent);
        }

        return $domDocument;
    }

    /**
     * Apply a process (xslt stylesheet) on an input (xml file) and save output.
     *
     * @internal Only saxon is currently supported.
     *
     * @param string $input Path of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If none, a temp file will
     * be used.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     */
    private function _processXsltViaExternal($input, $stylesheet, $output = '', $parameters = array())
    {
        if (empty($output)) {
            $output = tempnam(sys_get_temp_dir(), 'omk_');
        }

        $command = get_option('xml_import_xslt_processor');

        $command = sprintf($command, escapeshellarg($input), escapeshellarg($stylesheet), escapeshellarg($output));
        foreach ($parameters as $name => $parameter) {
            $command .= ' ' . escapeshellarg($name . '=' . $parameter);
        }

        $result = shell_exec($command . ' 2>&1 1>&-');
        @chmod($output, 0640);

        // In Shell, empty is a correct result.
        if (!empty($result)) {
            $msg = __('An error occurs during the xsl transformation of the file "%s" with the sheet "%s" : %s',
                $input, $stylesheet, $result);
            $this->_helper->flashMessenger($msg, 'error');
            return null;
        }

        return $output;
    }

    /**
     * Append and save a string into a file.
     *
     * @param string $filepath
     * @param string $filepath_to_append
     *
     * @return string|boolean
     *   $filepath if no error, false else.
     */
    private function _append_file($filepath, $filepath_to_append)
    {
        // Size of file to append is never bigger than some MB, so cat is not
        // used.
        if (file_put_contents($filepath, file_get_contents($filepath_to_append), FILE_APPEND | LOCK_EX) === false) {
            return false;
        }
        chmod($filepath, 0644);
        return $filepath;
    }

    /**
     * Returns a sanitized and unaccentued string for folder or file path.
     *
     * @param string $string The string to sanitize.
     *
     * @return string The sanitized string to use as a folder or a file name.
     */
    private function _sanitizeString($string)
    {
        return  substr($this->_convertNameToAscii($string), -200);
    }

    /**
     * Returns a sanitized string for folder or file path.
     *
     * The string should be a simple name, not a full path or url, because "/",
     * "\" and ":" are removed (so a path should be sanitized by part).
     *
     * @param string $string The string to sanitize.
     *
     * @return string The sanitized string.
     */
    private function _sanitizeName($string)
    {
        $string = strip_tags($string);
        $string = trim($string, ' /\\?<>:*%|"\'`&;');
        $string = preg_replace('/[\(\{]/', '[', $string);
        $string = preg_replace('/[\)\}]/', ']', $string);
        $string = preg_replace('/[[:cntrl:]\/\\\?<>:\*\%\|\"\'`\&\;#+\^\$\s]/', ' ', $string);
        return substr(preg_replace('/\s+/', ' ', $string), -250);
    }

    /**
     * Returns a sanitized and unaccentued string for folder or file name.
     *
     * @param string $string The string to convert to ascii.
     *
     * @return string The converted string to use as a folder or a file name.
     */
    private function _convertNameToAscii($string)
    {
        $string = $this->_sanitizeName($string);
        $string = htmlentities($string, ENT_NOQUOTES, 'utf-8');
        $string = preg_replace('#\&([A-Za-z])(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron)\;#', '\1', $string);
        $string = preg_replace('#\&([A-Za-z]{2})(?:lig)\;#', '\1', $string);
        $string = preg_replace('#\&[^;]+\;#', '_', $string);
        $string = preg_replace('/[^[:alnum:]\[\]_\-\.#~@+:]/', '_', $string);
        return substr(preg_replace('/_+/', '_', $string), -250);
    }

    /**
     * Convert Identifier field to name.
     *
     * It's simpler to manage identifiers by name, as they are in csv files.
     *
     * @param integer|string $postIdentifier
     * @return string
     */
    protected function _elementNameFromPost($postIdentifier)
    {
        $postIdentifier = trim($postIdentifier);
        if (empty($postIdentifier) || !is_numeric($postIdentifier)) {
            return $postIdentifier;
        }
        $element = get_record_by_id('Element', $postIdentifier);
        if (!$element) {
            return $postIdentifier;
        }
        return $element->set_name . ':' . $element->name;
    }

    /**
     * Return the list of standard delimiters.
     *
     * @return array The list of standard delimiters.
     */
    public static function getDelimitersList()
    {
        return array(
            'comma' => ',',
            'semi-colon' => ';',
            'pipe' => '|',
            'tabulation' => "\t",
            'carriage return' => "\r",
            'space' => ' ',
            'double space' => '  ',
            'empty' => '',
        );
    }

    /**
     * Return the list of standard enclosures.
     *
     * @return array The list of standard enclosures.
     */
    public static function getEnclosuresList()
    {
        return array(
            'double-quote' => '"',
            'quote' => "'",
            'empty' => '',
        );
    }
}
