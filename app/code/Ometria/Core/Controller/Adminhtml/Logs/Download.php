<?php
namespace Ometria\Core\Controller\Adminhtml\Logs;

use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;

class Download extends Action
{
    const ADMIN_RESOURCE = 'Ometria_Core::ometria_config';

    /** @var FileFactory */
    private $fileFactory;

    /** @var Filesystem */
    private $filesystem;

    /**
     * @param Action\Context $context
     * @param FileFactory $fileFactory
     * @param Filesystem $filesystem
     */
    public function __construct(
        Action\Context $context,
        FileFactory $fileFactory,
        Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->filesystem = $filesystem;
    }

    public function execute()
    {
        try {
            $path = 'log/ometria.log';
            $directory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
            if ($directory->isFile($path)) {
                return $this->fileFactory->create(
                    $path,
                    $directory->readFile($path),
                    DirectoryList::VAR_DIR
                );
            } else {
                $this->messageManager->addNotice(
                    __('Could not find Ometria log file.')->getText()
                );
                return $this->resultRedirectFactory->create()->setUrl($this->_redirect->getRefererUrl());
            }
        } catch (Exception $e) {
            $this->messageManager->addNotice(
                __('Something went wrong whilst downloading the Ometria log file.')->getText()
            );
            return $this->resultRedirectFactory->create()->setPath($this->_redirect->getRefererUrl());
        }
    }
}
