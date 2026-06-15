(function (global) {
    var SERVER_MAX_BYTES = 3 * 1024 * 1024;
    var TARGET = {
        profilePhoto: 512 * 1024,
        imageProof: 512 * 1024,
        chatAttachment: 512 * 1024,
        propertyImage: 2 * 1024 * 1024,
        document: 3 * 1024 * 1024
    };

    function formatBytesShort(bytes) {
        bytes = Number(bytes) || 0;
        if (bytes >= 1024 * 1024) {
            var mb = bytes / (1024 * 1024);
            var rounded = Math.round(mb * 10) / 10;
            return (Math.abs(rounded - Math.round(rounded)) < 0.05 ? String(Math.round(rounded)) : String(rounded).replace('.', ',')) + ' MB';
        }

        return String(Math.max(1, Math.round(bytes / 1024))) + ' KB';
    }

    function formatTargetLabel(key) {
        return formatBytesShort(TARGET[key] || SERVER_MAX_BYTES);
    }

    global.ImobilUploadLimits = {
        serverMaxBytes: SERVER_MAX_BYTES,
        target: TARGET,
        formatBytesShort: formatBytesShort,
        formatTargetLabel: formatTargetLabel
    };
})(window);
