# DrawIt (draw.io)

A WordPress plugin that interfaces with draw.io to create and edit diagrams inline while editing posts.

![Screenshot 2](screenshots/Screenshot_2.png)

## Description

DrawIt is a WordPress plugin that interfaces with the draw.io website to easily create beautiful diagrams, flow charts and drawings inline while you are editing a post. This powerful plugin saves the draw.io source code for your diagram and a PNG or SVG version of the image – providing crisp images that you can update without redrawing the diagram. There is also no hassle moving images back and forth between editors on your computer like typically is done without this plugin.

### Features

- Create diagrams directly within WordPress
- Multiple ways to add diagrams: Media Library, Visual Editor, or Text Editor
- Direct integration with draw.io's powerful interface
- Automatic source code saving for future editing
- Export as PNG or SVG

## Installation

1. Go to WP admin > Plugins > Add New
2. Search for "DrawIt" in the search field
3. Click "Install Now"
4. Click "Activate Plugin"

## Screenshots

| Screenshot | Description |
|------------|-------------|
| ![Creating/editing diagram](screenshots/Screenshot_1.png) | Button for creating/editing a diagram in TinyMCE Editor WordPress |
| ![Diagram interface](screenshots/Screenshot_2.png) | Diagram editing interface |
| ![Final result](screenshots/Screenshot_3.png) | Diagram image result after saving |

## FAQ

### How do I edit a diagram?

To edit a diagram that you've already created, just select it (e.g., the source code in the text post editor or the image itself in the visual post editor) and then click on the DrawIt button in the editor!

### Where is the source code for my diagram saved?

The source code for the diagram is saved with the image in your WordPress installation. As long as you do not delete the image from your media library, then you will be able to open and edit the image from the post/page editor where it is being used.

### How do I edit a diagram that is only in the media library?

For now, you'll have to insert it into a post to be able to edit it. We'll work on improving this in future releases.

## Roadmap

- Add option for saving draw.io XML source in the PNG or SVG directly, instead of only saving the source XML to the WP database

## Technical Details

- **Contributors**: Loc Hoang
- **Requires WordPress**: 4.0+
- **Tested up to**: 4.4
- **Stable tag**: 1.0
- **License**: [GPLv3](http://www.gnu.org/licenses/gpl-2.0.html)

## Notice

This plugin uses the [draw.io website](https://www.draw.io/), but is not affiliated with draw.io.

## Changelog

### 1.0

- Initial release
