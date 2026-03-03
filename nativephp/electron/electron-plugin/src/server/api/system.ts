import express from 'express';
import {BrowserWindow, systemPreferences, safeStorage, nativeTheme} from 'electron';

const router = express.Router();

router.get('/can-prompt-touch-id', (req, res) => {
    res.json({
        result: systemPreferences.canPromptTouchID(),
    });
});

router.post('/prompt-touch-id', async (req, res) => {
    try {
        await systemPreferences.promptTouchID(req.body.reason)

        res.sendStatus(200);
    } catch (e) {
        res.status(400).json({
            error: e.message,
        });
    }
});

router.get('/can-encrypt', (req, res) => {
    res.json({
        result: safeStorage.isEncryptionAvailable(),
    });
});

router.post('/encrypt', async (req, res) => {
    try {
        res.json({
            result: safeStorage.encryptString(req.body.string).toString('base64'),
        });
    } catch (e) {
        res.status(400).json({
            error: e.message,
        });
    }
});

router.post('/decrypt', async (req, res) => {
    try {
        res.json({
            result: safeStorage.decryptString(Buffer.from(req.body.string, 'base64')),
        });
    } catch (e) {
        res.status(400).json({
            error: e.message,
        });
    }
});

router.get('/printers', async (req, res) => {
    const existingWindow = BrowserWindow.getAllWindows()[0];
    let window = existingWindow;
    let createdTemporaryWindow = false;

    try {
        if (!window) {
            window = new BrowserWindow({ show: false });
            createdTemporaryWindow = true;
        }

        const printers = await window.webContents.getPrintersAsync();

        res.json({
            printers,
        });
    } catch (e) {
        res.status(500).json({
            printers: [],
            error: e.message,
        });
    } finally {
        if (createdTemporaryWindow && window && !window.isDestroyed()) {
            window.destroy();
        }
    }
});

router.post('/print', async (req, res) => {
    const {printer, html, settings} = req.body;

    let printWindow = new BrowserWindow({
        show: false,
    });

    const defaultSettings = {
        silent: true,
        deviceName: printer,
    };

    const mergedSettings = {
        ...defaultSettings,
        ...(settings && typeof settings === 'object' ? settings : {}),
    };

    printWindow.webContents.on('did-finish-load', () => {
        printWindow.webContents.print(mergedSettings, (success, errorType) => {
            if (success) {
                console.log('Print job completed successfully.');
                res.sendStatus(200);
            } else {
                console.error('Print job failed:', errorType);
                res.sendStatus(500);
            }
            if (printWindow) {
                printWindow.close(); // Close the window and the process
                printWindow = null;  // Free memory
            }
        });
    });

    await printWindow.loadURL(`data:text/html;charset=UTF-8,${html}`);
});

router.post('/print-to-pdf', async (req, res) => {
    const {html, settings} = req.body;

    let printWindow = new BrowserWindow({
        show: false,
    });

    printWindow.webContents.on('did-finish-load', () => {
        printWindow.webContents.printToPDF(settings ?? {}).then(data => {
            printWindow.close();
                res.json({
                    result: data.toString('base64'),
                });
        }).catch(e => {
            printWindow.close();

            res.status(400).json({
                error: e.message,
            });
        });
    });

    await printWindow.loadURL(`data:text/html;base64;charset=UTF-8,${html}`);
});

router.get('/theme', (req, res) => {
    res.json({
        result: nativeTheme.themeSource,
    });
});

router.post('/theme', (req, res) => {
    const { theme } = req.body;

    nativeTheme.themeSource = theme;

    res.json({
        result: theme,
    });
});

export default router;
