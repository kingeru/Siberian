<?php

/**
 * Class Installer_Model_Installer_Module_Parser
 */
class Installer_Model_Installer_Module_Parser extends Core_Model_Default
{
    /**
     * @var
     */
    protected $_tmp_file;

    /**
     * @var
     */
    protected $_tmp_directory;

    /**
     * @var
     */
    protected $_package_details;

    /**
     * @var
     */
    protected $_module_name;

    /**
     * @var
     */
    protected $_files;

    /**
     * @var
     */
    protected $__ftp;

    /**
     * @var array
     */
    protected $_files_to_delete = [];

    /**
     * @var
     */
    protected $_errors;

    /**
     * Installer_Model_Installer_Module_Parser constructor.
     * @param array $config
     * @throws Zend_Exception
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    /**
     * @param $file
     * @return $this
     */
    public function setFile($file)
    {
        $this->_tmp_file = $file;
        $infos = pathinfo($this->_tmp_file);
        $this->_module_name = $infos['filename'];
        $this->_tmp_directory = Core_Model_Directory::getTmpDirectory(true) . '/' . $this->_module_name;
        $this->_files = [];
        return $this;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function extract()
    {
        $tmp_dir = Core_Model_Directory::getTmpDirectory(true) . '/';

        if (!is_writable($tmp_dir)) {
            throw new \Siberian\Exception(__("#19-001 The folder %s is not writable. Please fix this issue and try again.", $tmp_dir));
        } else {

            if (is_dir($this->_tmp_directory)) {
                Core_Model_Directory::delete($this->_tmp_directory);
            }

            mkdir($this->_tmp_directory, 0777);

            # Extract to TMP Directory
            exec("unzip '{$this->_tmp_file}' -d '{$this->_tmp_directory}' 2>&1", $output);


            if (!count(glob($this->_tmp_directory))) {
                throw new Exception($this->_("#19-002 Unable to extract the archive. Please make sure that the 'zip' extension is installed."));
            }

            $base_path = $this->_tmp_directory . "/template.install.php";
            if (is_readable($base_path)) {
                $template_install_path = Core_Model_Directory::getBasePathTo('/var/tmp/template.install.php');
                rename($base_path, $template_install_path);
            }

            return $this->_tmp_directory;
        }
    }

    /**
     * @throws Exception
     */
    public function checkDependencies()
    {
        $package = $this->getPackageDetails();

        switch ($package->getType()) {
            case 'template':
            case 'module':
            case 'layout':
            case 'icons':
                $this->_checkDependenciesModule($package, $package->getType());
                break;
            default:
                $this->_checkDependenciesFallback($package);
                break;
        }
    }

    /**
     * @param $package
     * @param string $package_type
     * @throws Exception
     * @throws Zend_Exception
     */
    private function _checkDependenciesModule($package, $package_type = "module")
    {
        $deps = $package->getDependencies();

        foreach ($deps as $type => $values) {
            switch ($type) {
                case "system":
                    if (!empty($values["type"])) {
                        $_type = constant(strtoupper($values["type"]));
                        $_version = $values["version"];

                        if ($_type > constant(Siberian_Version::TYPE) || version_compare($_version, Siberian_Version::VERSION, ">")) {
                            throw new Exception(__("#19-014: Your system doesn't meet the requirements for this module, version >=%s is required.", $_version));
                        }
                    }
                    break;
                case "modules":
                    $missing_deps = [];
                    foreach ($values as $module_name => $module_version) {
                        $_module_deps = new Installer_Model_Installer_Module();
                        $_module_deps->prepare($module_name);

                        if (!$_module_deps->isInstalled() || version_compare($module_version, $_module_deps->getVersion(), ">")) {
                            $missing_deps[$module_name] = $module_version;
                        }
                    }
                    if (!empty($missing_deps)) {
                        $message = "#19-015: The module your are about to install requires the following ones, %s.";
                        $modules = [];
                        foreach ($missing_deps as $name => $version) {
                            $modules[] = "{$name}@{$version}";
                        }
                        $modules = join(", ", $modules);
                        throw new Exception(__($message, $modules));
                    }
                    break;
                default:
                    break;
            }
        }

        # Then check the module deps itself
        $_module = new Installer_Model_Installer_Module();
        $_module->prepare($name);

        dbg('--module--');
        dbg($_module);

        switch ($package_type) {
            case 'layout':
                $_module->setType('layout');
                break;
            case 'template':
                $_module->setType('template');
                break;
            case 'icons':
                $_module->setType('icons');
                break;
            case 'module':
                break;
        }

        if ($_module->isInstalled() && version_compare($version, $_module->getVersion(), "<=")) {
            throw new Exception(__("#19-016: You already have installed this %s or a newer version.", $package_type));
        } else {
            # Set the module as in Local, which could be uninstalled.
            $_module
                ->setCanUninstall(true)
                ->save();
        }
    }

    /**
     * @param $package
     * @throws Exception
     */
    private function _checkDependenciesFallback($package)
    {
        $dependencies = $package->getDependencies();
        if (!empty($dependencies) AND is_array($dependencies)) {
            foreach ($dependencies as $type => $dependency) {

                switch ($type) {

                    case "system":

                        if (strtolower($dependency["type"]) != strtolower(Siberian_Version::TYPE)) {
                            throw new Exception($this->_("#19-003: This update is designed for the %s, you can't install it in your %s.", $package->getName(), Siberian_Version::NAME));
                        }

                        # Remove all beta-parts from beta if in stable for requirements
                        if (__get("update_channel") == "stable") {
                            $version_parts = explode("-", $dependency["version"]);
                            $dependency["version"] = $version_parts[0];
                        }

                        # If the current version of Siberian equals the package's version
                        if (version_compare(Siberian_Version::VERSION, $package->getVersion(), "=")) {
                            throw new Exception(__("#19-004: You already have installed this update."));
                        } elseif (version_compare(Siberian_Version::VERSION, $dependency["version"], "<")) {
                            throw new Exception(__("#19-005: Please update your system to the %s version before installing this update.", $dependency["version"]));
                        }

                        break;

                    case "module":

                        $compare = version_compare(Siberian_Version::VERSION, $dependency["version"]);
                        if ($compare == -1) {
                            throw new Exception(__("#19-007: Please update your system to the %s version before installing this update.", $dependency["version"]));
                        }

                        break;

                    case "template":

                        $template_design = new Template_Model_Design();
                        $template_design->find($package->getCode(), "code");

                        if ($template_design->getId()) {
                            throw new Exception(__("#19-008: You already have installed this template."));
                        }

                        $compare = version_compare(Siberian_Version::VERSION, $dependency["version"]);
                        if ($compare == -1) {
                            throw new Exception(__("#19-009: Please update your system to the %s version before installing this update.", $dependency["version"]));
                        }

                        break;
                }
            }
        }
    }

    /**
     * @return Core_Model_Default
     * @throws Exception
     * @throws Zend_Exception
     */
    public function getPackageDetails()
    {
        if (!$this->_package_details) {

            $this->_package_details = new Core_Model_Default();
            $package_file = "{$this->_tmp_directory}/package.json";
            if (!file_exists($package_file)) {
                throw new Exception($this->_("#19-010: The package you have uploaded is invalid."));
            }

            try {
                $content = Zend_Json::decode(file_get_contents($package_file));
            } catch (Zend_Json_Exception $e) {
                Zend_Registry::get("logger")->sendException(print_r($e, true), "siberian_update_", false);
                throw new Exception($this->_("#19-011: The package you have uploaded is invalid."));
            }

            $this->_package_details->setData($content);

        }

        return $this->_package_details;
    }

    /**
     * @return bool
     * @throws Exception
     * @throws Zend_Exception
     */
    public function copy()
    {
        $this->_parse();
        $this->_prepareFilesToDelete();

        if (!$this->_delete()) {
            return false;
        }

        // Clear module in case of update
        if ($this->getPackageDetails()->getReplaceModule()) {
            $this->_clearModule();
        }

        if (!$this->_copy()) {
            return false;
        }

        Core_Model_Directory::delete($this->_tmp_directory);

        return true;

    }

    /**
     * @throws Exception
     * @throws Zend_Exception
     */
    private function _clearModule()
    {
        $moduleName = $this->getPackageDetails()->getData('name');
        $base = Core_Model_Directory::getBasePathTo('/app/local/modules/' . $moduleName . '/');

        if (is_dir($base)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, 4608),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                if (!$file->isDir() && $file->isFile()) {
                    $path = $file->getPathname();
                    unlink($path);
                }
            }

            $dirs = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, 4608),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($dirs as $dir) {
                if ($dir->isDir()) {
                    $path = $dir->getPathname();
                    unlink($path);
                }
            }
        }
    }

    /**
     * @return bool
     */
    public function checkPermissions()
    {

        $this->_parse();
        $this->_prepareFilesToDelete();

        foreach ($this->_files as $file) {
            $info = pathinfo($file['destination']);
            $dirname = $info['dirname'];
            if (is_dir($dirname) && !is_writable($dirname)) {
                $dirname = str_replace(Core_Model_Directory::getBasePathTo(), '', $dirname);
                $errors[] = $dirname;
            }
            if (is_file($file['destination']) && !is_writable($file['destination'])) {
                $filename = str_replace(Core_Model_Directory::getBasePathTo(), '', $file["destination"]);
                $errors[] = $filename;
            }
        }

        foreach ($this->_files_to_delete as $file) {
            if (is_file($file) && !is_writable($file)) {
                $filename = str_replace(Core_Model_Directory::getBasePathTo(), '', $file);
                $errors[] = $filename;
            }
        }

        if (!empty($errors)) {
            $errors = array_unique($errors);
            $message = '- ' . implode('<br /> - ', $errors);

            $this->_addError($message);

            return false;
        }

        return true;
    }

    /**
     * @return mixed
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * @param $error
     * @return $this
     */
    protected function _addError($error)
    {
        $this->_errors[] = $error;
        return $this;
    }

    /**
     * @param null $dirIterator
     * @throws Exception
     * @throws Zend_Exception
     */
    protected function _parse($dirIterator = null)
    {
        if (is_null($dirIterator)) {
            $dirIterator = new DirectoryIterator($this->_tmp_directory);
        }

        $is_module = (in_array($this->getPackageDetails()->getData("type"), ["module", "layout", "icons", "template"]));
        $module_name = $this->getPackageDetails()->getData("name");

        foreach ($dirIterator as $element) {
            if ($element->isDot()) {
                continue;
            }

            if ($element->isFile() || $element->isLink()) {
                if (!$is_module && ($element->getRealPath() == "{$this->_tmp_directory}/package.json")) {
                    continue;
                }

                $file_path = $element->isLink() ? $element->getPathname() : $element->getRealPath();

                # Source file
                $source = $file_path;

                # Destination
                $base = ($is_module) ? Core_Model_Directory::getBasePathTo("/app/local/modules/{$module_name}/") : Core_Model_Directory::getBasePathTo();
                $destination = str_replace("{$this->_tmp_directory}/", $base, $file_path);

                $this->_files[] = [
                    'source' => $source,
                    'destination' => $destination,
                ];

            } else if ($element->isDir()) {
                $this->_parse(new DirectoryIterator($element->getRealPath()));
            }
        }
    }

    /**
     * @return $this
     * @throws Exception
     * @throws Zend_Exception
     */
    protected function _prepareFilesToDelete()
    {
        $files = $this->getPackageDetails()->getFilesToDelete();
        foreach ($files as $file) {
            $this->_files_to_delete[] = $file;
        }

        return $this;
    }

    /**
     *
     */
    protected function _backup()
    {
        $files_list = [];
        $base_path = Core_Model_Directory::getBasePathTo();

        foreach ($this->_files_to_delete as $file) {
            $files_list[] = $file;
        }

        foreach ($this->_files as $file) {
            $files_list[] = str_replace($base_path, "", $file['destination']);
        }

        if (!empty($files_list)) {
            $version = Siberian_Version::VERSION;
            chdir($base_path);
            file_put_contents("./backup.txt", implode("\n", $files_list));
            exec("zip backup-{$version}.zip -@ < backup.txt");
            unlink("./backup.txt");
        } else {
            $this->_errors[] = "#19-012: Unable to make the pre-update backup.";
        }
    }

    /**
     * @return bool
     */
    protected function _delete()
    {
        foreach ($this->_files_to_delete as $file) {
            if (file_exists(Core_Model_Directory::getBasePathTo($file))) {
                unlink(Core_Model_Directory::getBasePathTo($file));
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function _copy()
    {
        $errors = [];
        foreach ($this->_files as $file) {
            $info = pathinfo($file['destination']);
            if (!is_dir($info['dirname'])) {
                if (!mkdir($info['dirname'], 0775, true)) {
                    if ($this->__getFtp()) {
                        if (!$this->__getFtp()->createDirectory($file)) {
                            $errors[] = $info['dirname'];
                        }
                    }
                }
            }
        }

        if (!empty($errors)) {
            $errors = array_unique($errors);
            if (count($errors) > 1) {
                $errors = implode('<br /> - ', $errors);
                $message = $this->_("The following folders are not writable: <br /> - %s", $errors);
            } else {
                $error = current($errors);
                $message = $this->_("#19-013: The folder %s is not writable.", $error);
            }

            $this->_addError($message);

            return false;

        } else {
            foreach ($this->_files as $file) {
                $is_copied = false;

                if (is_link($file['source'])) {
                    $is_copied = symlink(readlink($file['source']), $file['destination']);
                } else {
                    $is_copied = copy($file['source'], $file['destination']);
                }

                if (!$is_copied) {

                    $src = $file['source'];
                    $dst = str_replace(Core_Model_Directory::getBasePathTo(""), "", $file['destination']);

                    if ($this->__getFtp()) {
                        $this->__getFtp()->addFile($src, $dst);
                    }
                }
            }

            if ($this->__getFtp()) {
                $this->__getFtp()->send();
            }
        }

        return true;
    }

    /**
     * @return Siberian_Ftp
     */
    private function __getFtp()
    {

        if (!$this->__ftp) {
            $host = System_Model_Config::getValueFor("ftp_host");
            if ($host) {
                $user = System_Model_Config::getValueFor("ftp_username");
                $password = System_Model_Config::getValueFor("ftp_password");
                $port = System_Model_Config::getValueFor("ftp_port");
                $path = System_Model_Config::getValueFor("ftp_path");
                $this->__ftp = new Siberian_Ftp($host, $user, $password, $port, $path);
            }
        }

        return $this->__ftp;
    }
}
