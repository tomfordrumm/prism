export default function (secret) {
    return function (req, res, next) {
        const headerSecret = req.headers['x-nativephp-secret'];
        const expectedSecret = typeof secret === 'string' ? secret.trim() : '';
        const providedSecret = typeof headerSecret === 'string' ? headerSecret.trim() : '';

        if (expectedSecret === '' || providedSecret === '' || providedSecret !== expectedSecret) {
            res.sendStatus(403);
            return;
        }
        next();
    };
}
