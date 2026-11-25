// ============================================================================
// freedns.my - the Flash interface.
//
// Compiled with Haxe to assets/ui.swf:
//
//     haxe -swf ../assets/ui.swf -main Main -D swf-header=800:600:24:FFFFFF
//
// (Haxe 4 deprecated -swf-header; -D swf-header=... is the spelling that works.)
//
// A monospace console: a header, two rows of clickable words (screens, then languages),
// and either a block of text or - on the request screen - a real form, all fed by
// /flash-api.php. Absolute paths on purpose: the .swf lives in /assets/, so a relative
// "flash-api.php" would resolve to /assets/flash-api.php and 404.
//
// Line protocol, one record per line:
//
//     TITLE:/NAV:/LANG:/CURLANG:/LINE:/BLANK:/STATUS:      text screens
//     FORM:1                                               this screen is a form
//     ZONES:pvf.cc,nl0.net,…   TYPES:A,AAAA,CNAME,TXT      the choices
//     CSRF:<token>                                          the registry's own token
//     RESULT:ok|<message> / RESULT:err|<message>            the answer to a submission
//
// Choices are cycled by clicking, not by <select>: a combo box in Flash 7 on IE6 is a
// popup window with its own list of bugs, and this console has only five fields.
// ============================================================================

class Main
{
    static var W: Int = 800;
    static var H: Int = 600;

    static var root: flash.display.Sprite;
    static var titleField: flash.text.TextField;
    static var contentField: flash.text.TextField;
    static var statusField: flash.text.TextField;
    static var navSprite: flash.display.Sprite;
    static var langSprite: flash.display.Sprite;
    static var formSprite: flash.display.Sprite;

    static var current: String = "home";
    static var currentLang: String = "";

    // form state
    static var zones: Array<String> = [];
    static var types: Array<String> = [];
    static var zoneIx: Int = 0;
    static var typeIx: Int = 0;
    static var csrf: String = "";
    static var inLabel: flash.text.TextField;
    static var inValue: flash.text.TextField;
    static var inEmail: flash.text.TextField;
    static var zoneWord: flash.text.TextField;
    static var typeWord: flash.text.TextField;
    static var resultField: flash.text.TextField;

    // ---------------------------------------------------------------- helpers

    static function format(size: Int, color: Int, bold: Bool = false): flash.text.TextFormat
    {
        var f = new flash.text.TextFormat();
        f.font = "Courier New";
        f.size = size;
        f.color = color;
        f.bold = bold;
        return f;
    }

    static function makeField(x: Float, y: Float, w: Float, h: Float, size: Int, color: Int): flash.text.TextField
    {
        var tf = new flash.text.TextField();
        tf.x = x; tf.y = y; tf.width = w; tf.height = h;
        tf.multiline = true; tf.wordWrap = true; tf.selectable = false;
        tf.defaultTextFormat = format(size, color);
        tf.setTextFormat(format(size, color));
        root.addChild(tf);
        return tf;
    }

    static function makeInput(x: Float, y: Float, w: Float, size: Int): flash.text.TextField
    {
        var tf = new flash.text.TextField();
        tf.x = x; tf.y = y; tf.width = w; tf.height = 22;
        tf.border = true;
        tf.background = true;
        tf.backgroundColor = 0xFFFFFF;
        tf.borderColor = 0x999999;
        tf.type = flash.text.TextFieldType.INPUT;
        tf.selectable = true;
        tf.multiline = false;
        tf.defaultTextFormat = format(size, 0x111111);
        root.addChild(tf);
        return tf;
    }

    static function rule(y: Float): Void
    {
        var s = new flash.display.Sprite();
        s.graphics.beginFill(0xBBBBBB);
        s.graphics.drawRect(20, y, W - 40, 1);
        s.graphics.endFill();
        root.addChild(s);
    }

    static function word(parent: flash.display.Sprite, x: Float, y: Float, label: String, onClick: Void->Void): Float
    {
        var tf = new flash.text.TextField();
        tf.x = x; tf.y = y; tf.width = 16 + label.length * 9; tf.height = 20;
        tf.selectable = false;
        tf.text = label;
        tf.defaultTextFormat = format(14, 0x0000EE);
        tf.setTextFormat(tf.defaultTextFormat);
        if (onClick != null)
        {
            tf.addEventListener(flash.events.MouseEvent.CLICK, function(e) { onClick(); });
            tf.addEventListener(flash.events.MouseEvent.MOUSE_OVER, function(e) { tf.textColor = 0xEE0000; });
            tf.addEventListener(flash.events.MouseEvent.MOUSE_OUT, function(e) { tf.textColor = 0x0000EE; });
        }
        parent.addChild(tf);
        return tf.width;
    }

    static function dot(parent: flash.display.Sprite, x: Float, y: Float): Void
    {
        var tf = new flash.text.TextField();
        tf.x = x; tf.y = y; tf.width = 12; tf.height = 20;
        tf.selectable = false; tf.text = "\u00B7";
        tf.defaultTextFormat = format(14, 0x666666);
        parent.addChild(tf);
    }

    // ------------------------------------------------------------------ load

    static function apiUrl(screen: String): String
    {
        var url = "/flash-api.php?screen=" + screen + "&t=" + Math.round(Math.random() * 1e9);
        if (currentLang != "") { url += "&lang=" + currentLang; }
        return url;
    }

    static function loadScreen(screen: String): Void
    {
        current = screen;
        statusField.text = "loading " + screen + " ...";

        var request = new flash.net.URLRequest(apiUrl(screen));
        var loader = new flash.net.URLLoader();
        loader.addEventListener(flash.events.Event.COMPLETE, function(e) {
            render(Std.string(loader.data));
        });
        loader.addEventListener(flash.events.IOErrorEvent.IO_ERROR, function(e) {
            statusField.text = "/flash-api.php could not be reached.";
        });
        loader.load(request);
    }

    static function render(data: String): Void
    {
        var lines = data.split("\n");
        var nav: Array<Array<String>> = [];
        var langs: Array<Array<String>> = [];
        var body = new StringBuf();
        var curLang = currentLang;
        var isForm = false;
        var result = "";

        for (i in 0...lines.length)
        {
            var line = StringTools.replace(lines[i], "\r", "");
            if (line.length < 3) { continue; }
            var colon = line.indexOf(":");
            if (colon < 1) { continue; }
            var key = line.substr(0, colon);
            var value = line.substr(colon + 1);

            switch (key)
            {
                case "TITLE":
                    titleField.text = value;
                    titleField.setTextFormat(format(22, 0x111111, true));
                case "NAV":
                    var parts = value.split("|");
                    if (parts.length >= 2) { nav.push([parts[0], parts[1]]); }
                case "LANG":
                    var lp = value.split("|");
                    if (lp.length >= 2) { langs.push([lp[0], lp[1]]); }
                case "CURLANG":
                    curLang = value; currentLang = value;
                case "LINE":
                    body.add(value); body.add("\n");
                case "BLANK":
                    body.add("\n");
                case "STATUS":
                    statusField.text = value;
                case "FORM":
                    isForm = true;
                case "ZONES":
                    zones = value.split(",");
                    if (zoneIx >= zones.length) { zoneIx = 0; }
                case "TYPES":
                    types = value.split(",");
                    if (typeIx >= types.length) { typeIx = 0; }
                case "CSRF":
                    csrf = value;
                case "RESULT":
                    var rp = value.split("|");
                    result = (rp.length >= 2 ? rp[1] : value);
                default:
            }
        }

        drawNav(nav);
        drawLangs(langs, curLang);

        if (isForm)
        {
            contentField.text = body.toString();
            contentField.setTextFormat(format(14, 0x111111));
            buildForm(result);
        }
        else
        {
            clearForm();
            contentField.text = body.toString();
            contentField.setTextFormat(format(14, 0x111111));
        }
    }

    static function drawNav(items: Array<Array<String>>): Void
    {
        if (navSprite != null && navSprite.parent != null) { root.removeChild(navSprite); }
        navSprite = new flash.display.Sprite();
        root.addChild(navSprite);

        var x: Float = 20;
        var y: Float = 72;

        for (i in 0...items.length)
        {
            var screen = items[i][0];
            var label = items[i][1];
            if (screen == current)
            {
                var tf = new flash.text.TextField();
                tf.x = x; tf.y = y; tf.width = 16 + label.length * 9; tf.height = 20;
                tf.selectable = false; tf.text = label;
                tf.defaultTextFormat = format(14, 0x111111, true);
                tf.setTextFormat(tf.defaultTextFormat);
                navSprite.addChild(tf);
                x += tf.width + 12;
            }
            else
            {
                x += word(navSprite, x, y, label, function() { loadScreen(screen); }) + 12;
            }
            if (i < items.length - 1) { dot(navSprite, x - 10, y); }
        }
    }

    static function drawLangs(items: Array<Array<String>>, cur: String): Void
    {
        if (langSprite != null && langSprite.parent != null) { root.removeChild(langSprite); }
        langSprite = new flash.display.Sprite();
        root.addChild(langSprite);

        var x: Float = 20;
        var y: Float = 96;

        var head = new flash.text.TextField();
        head.x = x; head.y = y; head.width = 60; head.height = 20;
        head.selectable = false; head.text = "lang:";
        head.defaultTextFormat = format(12, 0x666666);
        langSprite.addChild(head);
        x += 54;

        for (i in 0...items.length)
        {
            var code = items[i][0];
            var label = items[i][1];
            var tf = new flash.text.TextField();
            tf.x = x; tf.y = y; tf.width = 16 + label.length * 8; tf.height = 20;
            tf.selectable = false;
            tf.text = label;
            if (code == cur)
            {
                tf.defaultTextFormat = format(12, 0x111111, true);
                tf.setTextFormat(tf.defaultTextFormat);
            }
            else
            {
                tf.defaultTextFormat = format(12, 0x0000EE);
                tf.setTextFormat(tf.defaultTextFormat);
                tf.addEventListener(flash.events.MouseEvent.CLICK, function(e) {
                    currentLang = code;
                    loadScreen(current);
                });
                tf.addEventListener(flash.events.MouseEvent.MOUSE_OVER, function(e) { tf.textColor = 0xEE0000; });
                tf.addEventListener(flash.events.MouseEvent.MOUSE_OUT, function(e) { tf.textColor = 0x0000EE; });
            }
            langSprite.addChild(tf);
            x += tf.width + 10;
        }
    }

    // ------------------------------------------------------------------ form

    static function clearForm(): Void
    {
        if (formSprite != null && formSprite.parent != null) { root.removeChild(formSprite); }
        formSprite = null;
        inLabel = null; inValue = null; inEmail = null;
        zoneWord = null; typeWord = null; resultField = null;
    }

    static function buildForm(result: String): Void
    {
        clearForm();
        formSprite = new flash.display.Sprite();
        root.addChild(formSprite);

        var y: Float = 200;

        // zone: click to cycle
        var zl = new flash.text.TextField();
        zl.x = 20; zl.y = y; zl.width = 110; zl.height = 22; zl.selectable = false;
        zl.text = "zone:";
        zl.defaultTextFormat = format(14, 0x111111);
        formSprite.addChild(zl);

        zoneWord = new flash.text.TextField();
        zoneWord.x = 130; zoneWord.y = y; zoneWord.width = 300; zoneWord.height = 22;
        zoneWord.selectable = false;
        zoneWord.text = (zones.length > 0 ? zones[zoneIx] + "  (click to change)" : "(no open zone)");
        zoneWord.defaultTextFormat = format(14, 0x0000EE);
        zoneWord.setTextFormat(zoneWord.defaultTextFormat);
        zoneWord.addEventListener(flash.events.MouseEvent.CLICK, function(e) {
            if (zones.length > 1) { zoneIx = (zoneIx + 1) % zones.length; }
            buildForm("");
        });
        formSprite.addChild(zoneWord);
        y += 30;

        // label
        var ll = new flash.text.TextField();
        ll.x = 20; ll.y = y; ll.width = 110; ll.height = 22; ll.selectable = false;
        ll.text = "name:";
        ll.defaultTextFormat = format(14, 0x111111);
        formSprite.addChild(ll);
        inLabel = makeInput(130, y, 300, 14);
        formSprite.addChild(inLabel);
        y += 30;

        // record type: click to cycle
        var tl = new flash.text.TextField();
        tl.x = 20; tl.y = y; tl.width = 110; tl.height = 22; tl.selectable = false;
        tl.text = "type:";
        tl.defaultTextFormat = format(14, 0x111111);
        formSprite.addChild(tl);

        typeWord = new flash.text.TextField();
        typeWord.x = 130; typeWord.y = y; typeWord.width = 300; typeWord.height = 22;
        typeWord.selectable = false;
        typeWord.text = (types.length > 0 ? types[typeIx] + "  (click to change)" : "A");
        typeWord.defaultTextFormat = format(14, 0x0000EE);
        typeWord.setTextFormat(typeWord.defaultTextFormat);
        typeWord.addEventListener(flash.events.MouseEvent.CLICK, function(e) {
            if (types.length > 1) { typeIx = (typeIx + 1) % types.length; }
            buildForm("");
        });
        formSprite.addChild(typeWord);
        y += 30;

        // value
        var vl = new flash.text.TextField();
        vl.x = 20; vl.y = y; vl.width = 110; vl.height = 22; vl.selectable = false;
        vl.text = "address:";
        vl.defaultTextFormat = format(14, 0x111111);
        formSprite.addChild(vl);
        inValue = makeInput(130, y, 460, 14);
        formSprite.addChild(inValue);
        y += 30;

        // email
        var el = new flash.text.TextField();
        el.x = 20; el.y = y; el.width = 110; el.height = 22; el.selectable = false;
        el.text = "e-mail:";
        el.defaultTextFormat = format(14, 0x111111);
        formSprite.addChild(el);
        inEmail = makeInput(130, y, 300, 14);
        formSprite.addChild(inEmail);
        y += 40;

        // submit
        var btn = new flash.text.TextField();
        btn.x = 130; btn.y = y; btn.width = 120; btn.height = 26;
        btn.selectable = false;
        btn.background = true;
        btn.backgroundColor = 0xF2F2F2;
        btn.border = true;
        btn.borderColor = 0x999999;
        btn.text = "  submit";
        btn.defaultTextFormat = format(14, 0x000000);
        btn.setTextFormat(btn.defaultTextFormat);
        btn.addEventListener(flash.events.MouseEvent.CLICK, function(e) { submit(); });
        btn.addEventListener(flash.events.MouseEvent.MOUSE_OVER, function(e) { btn.backgroundColor = 0xFFFFCC; });
        btn.addEventListener(flash.events.MouseEvent.MOUSE_OUT, function(e) { btn.backgroundColor = 0xF2F2F2; });
        formSprite.addChild(btn);

        // result line
        resultField = new flash.text.TextField();
        resultField.x = 20; resultField.y = y + 40; resultField.width = W - 40; resultField.height = 60;
        resultField.selectable = false;
        resultField.wordWrap = true;
        resultField.text = result;
        resultField.defaultTextFormat = format(13, 0x006600);
        resultField.setTextFormat(resultField.defaultTextFormat);
        formSprite.addChild(resultField);

        // keep the reference on the stage so clicks reach it
        root.addChild(formSprite);
    }

    static function submit(): Void
    {
        if (inLabel == null || inValue == null || inEmail == null) { return; }

        var zone = (zones.length > 0 ? zones[zoneIx] : "");
        var type = (types.length > 0 ? types[typeIx] : "A");

        var vars = new flash.net.URLVariables();
        vars.zone = zone;
        vars.label = inLabel.text;
        vars.type = type;
        vars.value = inValue.text;
        vars.email = inEmail.text;
        vars.csrf = csrf;
        vars.action = "submit";

        var request = new flash.net.URLRequest(apiUrl("request"));
        request.method = flash.net.URLRequestMethod.POST;
        request.data = vars;

        statusField.text = "submitting ...";

        var loader = new flash.net.URLLoader();
        loader.addEventListener(flash.events.Event.COMPLETE, function(e) {
            render(Std.string(loader.data));
        });
        loader.addEventListener(flash.events.IOErrorEvent.IO_ERROR, function(e) {
            statusField.text = "the submission could not be sent.";
        });
        loader.load(request);
    }

    // ------------------------------------------------------------------ main

    static function main(): Void
    {
        root = flash.Lib.current;

        var bg = new flash.display.Sprite();
        bg.graphics.beginFill(0xFFFFFF);
        bg.graphics.drawRect(0, 0, W, H);
        bg.graphics.endFill();
        root.addChild(bg);

        titleField = makeField(20, 20, W - 40, 34, 22, 0x111111);
        titleField.text = "freedns.my";
        titleField.setTextFormat(format(22, 0x111111, true));

        rule(58);
        rule(116);
        rule(H - 44);

        statusField = makeField(20, H - 34, W - 40, 22, 12, 0x666666);
        statusField.text = "";

        contentField = makeField(20, 126, W - 40, 70, 14, 0x111111);

        loadScreen(current);
    }
}
