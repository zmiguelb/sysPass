<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Services\Task;

use SP\Core\Context\SessionContext;
use SP\Core\Messages\TaskMessage;
use SP\Util\Util;

/**
 * Class Task
 *
 * @package SP\Core
 */
final class Task
{
    /**
     * @var string Nombre de la tarea
     */
    protected $name;
    /**
     * @var string ID de la tarea
     */
    protected $taskId;
    /**
     * @var string Ruta y archivo salida de la tarea
     */
    protected $fileOut;
    /**
     * @var string Ruta y archivo de la tarea
     */
    protected $fileTask;
    /**
     * @var resource Manejador del archivo
     */
    protected $fileHandler;
    /**
     * @var int Intérvalo en segundos
     */
    protected $interval = 5;
    /**
     * @var bool Si se ha inicializado para escribir en el archivo
     */
    protected $initialized = false;
    /**
     * @var string
     */
    protected $uid;

    /**
     * Task constructor.
     *
     * @param string $name Nombre de la tarea
     * @param string $id
     */
    public function __construct($name, $id)
    {
        $this->name = $name;
        $this->taskId = $id;
        $this->initialized = $this->checkFile();
        $this->uid = $this->genUid();
    }

    /**
     * Comprobar si se puede escribir en el archivo
     *
     * @return bool
     */
    protected function checkFile()
    {
        $tempDir = Util::getTempDir();

        if ($tempDir !== false) {
            $this->fileOut = $tempDir . DIRECTORY_SEPARATOR . $this->taskId . '.out';
            $this->fileTask = $tempDir . DIRECTORY_SEPARATOR . $this->taskId . '.task';

            $this->deleteTaskFiles();

            return true;
        }

        return false;
    }

    /**
     * Eliminar los archivos de la tarea no usados
     */
    protected function deleteTaskFiles()
    {
        $filesOut = dirname($this->fileOut) . DIRECTORY_SEPARATOR . $this->taskId . '*.out';
        $filesTask = dirname($this->fileOut) . DIRECTORY_SEPARATOR . $this->taskId . '*.task';

        array_map('unlink', glob($filesOut));
        array_map('unlink', glob($filesTask));
    }

    /**
     * @return string
     */
    public function genUid()
    {
        return md5($this->name . $this->taskId);
    }

    /**
     * Generar un ID de tarea
     *
     * @param $name
     *
     * @return string
     */
    public static function genTaskId($name)
    {
        return uniqid($name);
    }

    /**
     * Iniciar la tarea
     *
     * @return bool
     */
    public function start()
    {
        return $this->openFile();
    }

    /**
     * Abrir el archivo para escritura
     *
     * @return  bool
     */
    protected function openFile()
    {
        if ($this->initialized === false
            || !$this->fileHandler = fopen($this->fileOut, 'wb')
        ) {
            return false;
        }

        return true;
    }

    /**
     * Escribir el tado de la tarea a un archivo
     *
     * @param TaskMessage $Message
     *
     * @return bool
     */
    public function writeStatus(TaskMessage $Message)
    {
        if ($this->initialized === false
            || !is_resource($this->fileHandler)
        ) {
            return false;
        }

        fwrite($this->fileHandler, $Message->composeText());

        return true;
    }

    /**
     * Escribir el tado de la tarea a un archivo
     *
     * @param TaskMessage $Message
     *
     * @return bool
     */
    public function writeStatusAndFlush(TaskMessage $Message)
    {
        return $this->initialized === true
            && !is_resource($this->fileHandler)
            && file_put_contents($this->fileOut, $Message->composeText()) !== false;
    }

    /**
     * Escribir un mensaje en el archivo de la tarea en formato JSON
     *
     * @param TaskMessage $Message
     *
     * @return bool
     */
    public function writeJsonStatusAndFlush(TaskMessage $Message)
    {
        return $this->initialized === true
            && !is_resource($this->fileHandler)
            && file_put_contents($this->fileOut, $Message->composeJson()) !== false;
    }

    /**
     * Iniciar la tarea
     *
     * @param bool $startSession
     *
     * @return bool
     */
    public function end($startSession = true)
    {
        if ($startSession) {
            session_start();
        }

        $this->deregister();

        return $this->closeFile() && @unlink($this->fileOut);
    }

    /**
     * Desregistrar la tarea en la sesión
     */
    public function deregister()
    {
        logger('Deregister Task: ' . $this->name);

        return unlink($this->fileTask);
    }

    /**
     * Abrir el archivo para escritura
     *
     * @return  bool
     */
    protected function closeFile()
    {
        if ($this->initialized === true && is_resource($this->fileHandler)) {
            return fclose($this->fileHandler);
        }

        return $this->initialized;
    }

    /**
     * @return int
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * @param int $interval
     *
     * @return Task
     */
    public function setInterval($interval)
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * @return string
     */
    public function getTaskId()
    {
        return $this->taskId;
    }

    /**
     * @return string
     */
    public function getFileOut()
    {
        return $this->fileOut;
    }

    /**
     * Registrar la tarea en la sesión.
     *
     * Es necesario bloquear la sesión para permitir la ejecución de otros scripts
     *
     * @param bool $lockSession Bloquear la sesión
     *
     * @return Task
     */
    public function register($lockSession = true)
    {
        logger('Register Task: ' . $this->name);

        file_put_contents($this->fileTask, serialize($this));

        if ($lockSession === true) {
            SessionContext::close();
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getFileTask()
    {
        return $this->fileTask;
    }

    /**
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }
}