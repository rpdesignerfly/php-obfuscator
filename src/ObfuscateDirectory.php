<?php
/**
 * @see       https://github.com/rpdesignerfly/artisan-obfuscator
 * @copyright Copyright (c) 2018 Ricardo Pereira Dias (https://rpdesignerfly.github.io)
 * @license   https://github.com/rpdesignerfly/artisan-obfuscator/blob/master/license.md
 */

declare(strict_types=1);

namespace PhpObfuscator;

class ObfuscateDirectory extends ObfuscateFile
{
    /**
     * Caminho completo até o diretório contendo
     * os arquivos que devem ser ofuscados.
     *
     * @var string
     */
    protected $plain_path = null;

    /**
     * Caminho completo até o diretório onde os arquivos
     * ofuscados serão salvos.
     *
     * @var string
     */
    protected $obfuscated_path = null;

    /**
     * Nome do arquivo que que conterá as funções de reversão.
     * Este arquivo sejá gerado pelo processo de ofuscação automaticamente
     * e adicionado no arquivo 'autoloader.php' da aplicação.
     *
     * @var string
     */
    protected $unpack_file = 'App.php';

    /**
     * Armazena as mensagens disparadas pelo processo de ofuscação.
     *
     * @var array
     */
    protected $errors_messages = [];

    /**
     * Links encontrados no processo de ofuscação.
     *
     * @var array
     */
    protected $links = [];

    /**
     * Adiciona uma mensagem na pilha de erros.
     *
     * @param string $message
     * @return bool
     */
    protected function addErrorMessage(string $message) : bool
    {
        $this->errors_messages[] = $message;
        return true;
    }

    /**
     * Seta o caminho completo até o diretório contendo
     * os arquivos que devem ser ofuscados.
     *
     * @param string $path
     * @return bool
     */
    protected function setPlainPath(string $path): bool
    {
        $this->plain_path = rtrim($path, "/");
        if (is_dir($this->plain_path) === false) {
            $this->addErrorMessage("The directory specified is invalid: {$this->plain_path}");
            return false;
        }

        if (is_readable($this->plain_path) == false) {
            $this->addErrorMessage("No permissions to read directory: {$this->plain_path}");
            return false;
        }

        return true;
    }

    /**
     * Devolve a localização do diretório contendo
     * os arquivos que devem ser ofuscados.
     *
     * @return string
     */
    protected function getPlainPath()
    {
        return $this->plain_path;
    }

    /**
     * Seta o caminho completo até o diretório contendo
     * os arquivos que devem ser ofuscados.
     *
     * @param string $path
     * @return Obfuscator\Libs\PhpObfuscator
     */
    protected function setObfuscatedPath(string $path) : bool
    {
        $this->obfuscated_path = rtrim($path, "/");
        if(is_dir($this->obfuscated_path) === false) {
            $this->addErrorMessage("The directory specified is invalid: {$this->obfuscated_path}");
            return false;
        }

        if (is_writable($this->obfuscated_path) == false) {
            $this->addErrorMessage("No permissions to write to the directory: {$this->obfuscated_path}");
            return false;
        }

        return true;
    }

    /**
     * Devolve a localização do diretório onde os arquivos
     * ofuscados devem ser salvos.
     *
     * @return string
     */
    protected function getObfuscatedPath()
    {
        $base_name = \pathinfo($this->plain_path, PATHINFO_BASENAME);

        return is_null($this->obfuscated_path)
            ? dirname($this->plain_path) . DIRECTORY_SEPARATOR . $base_name
            : $this->obfuscated_path;
    }

    /**
     * Devolve a localização do arquivo que conterá as funções de reversão.
     * Este arquivo sejá gerado pelo processo de ofuscação automaticamente
     * e adicionado no arquivo 'autoloader.php' da aplicação.
     *
     * @return string
     */
    protected function getUnpackFile()
    {
        return $this->getObfuscatedPath() . DIRECTORY_SEPARATOR . $this->unpack_file;
    }

    /**
     * Varre o o diretório especificado, ofuscando os arquivos e
     * salvando no diretório de destino.
     *
     * @param  string $path_plain
     * @param  string $path_obfuscated
     * @return boolean
     */
    protected function obfuscateDirectory(string $path_plain, string $path_obfuscated) : bool
    {
        // Lista os arquivos do diretório
        $list = scandir($path_plain);
        if (count($list) == 2) { // '.', '..'
            // se não houverem arquivos, ignora o diretório
            return true;
        }

        // Cria o mesmo diretório para os arquivos ofuscados
        if ($this->makeDir($path_obfuscated) == false) {
            return false;
        }

        foreach ($list as $item) {

            if (in_array($item, ['.', '..']) ) {
                continue;
            }

            $iterate_current_item    = $path_plain . DIRECTORY_SEPARATOR . $item;
            $iterate_obfuscated_item = $path_obfuscated . DIRECTORY_SEPARATOR . $item;

            if (is_link($iterate_current_item)) {

                // LINKS
                // TODO: recriar os links
                $link = readlink($iterate_current_item);
                $this->links[$iterate_current_item] = $link;
                continue;

            } elseif (is_file($iterate_current_item) == true) {

                if ($this->isPhpFile($iterate_current_item) == true) {

                    // Arquivos PHP são ofuscados
                    if($this->obfuscateFile($iterate_current_item) == false) {
                        $this->addErrorMessage("Ocorreu um erro ao tentar ofuscar o arquivo: {$iterate_current_item}");
                        return false;
                    }

                    if ($this->save($iterate_obfuscated_item) == false) {
                        $this->addErrorMessage("Ocorreu um erro ao tentar salvar o arquivo ofuscado: {$iterate_obfuscated_item}");
                        return false;
                    }

                } else {

                    // Arquivos não-PHP são simplesmente copiados
                    if (copy($iterate_current_item, $iterate_obfuscated_item) === false) {
                        $this->addErrorMessage("Arquivo " . $iterate_current_item . " não pôde ser copiado");
                        return false;
                    }
                }

            } elseif (is_dir($iterate_current_item) ) {

                if ($this->obfuscateDirectory($iterate_current_item, $iterate_obfuscated_item) == false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Cria o diretório especificado no sistema de arquivos.
     *
     * @param  string  $path
     * @param  boolean $force
     * @return boolean
     */
    protected function makeDir(string $path, bool $force = false) : bool
    {
        if (is_dir($path) && is_writable($path) == false) {
            // Diretório já existe, mas não é gravável
            $this->addErrorMessage("O diretório {$path} já existe mas você não tem permissão para escrever nele");
            return false;

        } elseif (is_dir($path) == true) {
            // O diretório já existe
            return true;
        }

        if (@mkdir($path, 0755, $force) == true) {
            return true;
        }

        return false;
    }

    /**
     * Verifica se o nome especificado é para um arquivo PHP.
     *
     * @param  string $filename
     * @return boolean
     */
    protected function isPhpFile(string $filename) : bool
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return (strtolower($extension) == 'php');
    }

    /**
     * Configura os arquivos resultantes do processo de ofuscação
     * para que funcionem no mesmo ambiente que os originais funcionavem.
     *
     * @return bool
     */
    public function setupAutoloader() : bool
    {
        $path_plain = $this->getPlainPath();

        // Gera uma lista com todos os arquivos PHP
        // que foram ofuscados
        $index = $this->makeIndex($path_plain);

        // Salva o arquivo contendo as funções
        // que desfazem a ofuscação do código
        $revert_file = $this->getUnpackFile();
        if($this->getObfuscator()->saveRevertFile($revert_file) == false) {
            $this->addErrorMessage("Ocorreu um erro ao tentar criar o arquivo de reversão");
            return false;
        }

        // Adiciona o arquivo de reversão como o
        // primeiro da lista no autoloader
        $index = array_merge([$revert_file], $index);

        // Cria o autoloader com os arquivos ofuscados
        if ($this->generateAutoloader($index) == false) {
            $this->addErrorMessage("Não foi possível gerar o autoloader em {$path_plain}");
            return false;
        }

        return true;
    }

    /**
     * Devogera uma lista com os arquivos php
     * contidos no diretório especificado.
     *
     * @param  string $destiny
     * @return array
     */
    protected function makeIndex(string $path) : array
    {
        $index = [];

        $list = scandir($path);
        foreach ($list as $item) {

            if (in_array($item, ['.', '..']) ) {
                continue;
            }

            $iterator_index_item = $path . DIRECTORY_SEPARATOR . $item;

            if (is_link($iterator_index_item)) {
                // LINKS
                // são ignorados neste ponto
                continue;

            } elseif (is_file($iterator_index_item) == true) {

                if ($this->isPhpFile($iterator_index_item) == true) {
                    $index[] = $iterator_index_item;
                } else {
                    // Arquivos não-PHP
                    // são ignorados neste ponto
                    continue;
                }

            } elseif (is_dir($iterator_index_item) ) {
                // DIRETÓRIOS
                $list = $this->makeIndex($iterator_index_item);
                foreach ($list as $file) {
                    $index[] = $file;
                }
            }
        }

        return $index;
    }

    /**
     * Gera um carregador para os arquivos ofuscados.
     *
     * @param  array $list_files
     * @return bool
     */
    protected function generateAutoloader(array $list_files) : bool
    {
        $file = $this->getPlainPath() . DIRECTORY_SEPARATOR . 'autoloader.php';

        // Se o autoloader existir, remove-o da lista
        // TODO: refatorar para isso não ser necessário jamais!!
        if (($key = array_search($file, $list_files)) !== false) {
            unset($list_files[$key]);
        }

        $contents = "<?php \n\n";

        $contents .= "\$includes = array(\n";
        $contents .= "    '" . implode("',\n    '", $list_files) . "'\n";
        $contents .= ");\n\n";

        $contents .= "foreach(\$includes as \$file) {\n";
        $contents .= "    require_once(\$file);\n";
        $contents .= "}\n\n";

        return (file_put_contents($file, $contents) !== false);
    }
}
