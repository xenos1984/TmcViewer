OpenLayers.Renderer.symbol.arrow = [0, 5, 3, 2, 1, 2, 1, -5, -1, -5, -1, 2, -3, 2, 0, 5];
var map;
var osmdata;
var tmcdata;
var losm; // OSM Data Layer
var geojson = new OpenLayers.Format.GeoJSON({
	internalProjection: new OpenLayers.Projection("EPSG:900913"),
	externalProjection: new OpenLayers.Projection("EPSG:4326")
});
var dataExtent;
var setExtent = function()
{
	if(dataExtent)
		dataExtent.extend(this.getDataExtent());
	else
		dataExtent = this.getDataExtent();
	map.zoomToExtent(dataExtent);
};

function init()
{
	if(!document.getElementById("map"))
		return;

	init_features();

	map = new OpenLayers.Map ("map", {
		controls:[
			new OpenLayers.Control.Navigation(),
			new OpenLayers.Control.PanZoomBar(),
			new OpenLayers.Control.LayerSwitcher(),
			new OpenLayers.Control.ScaleLine(),
			new OpenLayers.Control.Attribution()],
		maxExtent: new OpenLayers.Bounds(-20037508.34,-20037508.34,20037508.34,20037508.34),
		maxResolution: 156543.0399,
		numZoomLevels: 20,
		units: 'm',
		projection: new OpenLayers.Projection("EPSG:900913"),
		displayProjection: new OpenLayers.Projection("EPSG:4326"),
		zoomMethod: null
	} );
/*
	map.addLayer(new OpenLayers.Layer.OSM.Mapnik("Mapnik"));
*/
	map.addLayers([
		new OpenLayers.Layer.OSM.Mapnik("Mapnik"),
		new OpenLayers.Layer.OSM.CycleMap("CycleMap"),
		new OpenLayers.Layer.OSM.TransportMap("TransportMap"),
		new OpenLayers.Layer.XYZ("OSM German", ["http://a.tile.openstreetmap.de/tiles/osmde/${z}/${x}/${y}.png", "http://b.tile.openstreetmap.de/tiles/osmde/${z}/${x}/${y}.png", "http://c.tile.openstreetmap.de/tiles/osmde/${z}/${x}/${y}.png", "http://d.tile.openstreetmap.de/tiles/osmde/${z}/${x}/${y}.png"], {numZoomLevels: 19, attribution: '<a href="./germanstyle.html">About style</a>'})
	]);

	var layers = [];

	function styleOSM(feature) {
		// check feature Role
		var parts = feature.attributes.role.split(":");
		if(parts.length < 1 || parts[0] == "") { // adding both if no role is set
			parts[0] = "both";
			parts[1] = "empty";
		}
		if(parts.length < 2) {
			if(parts[0] == "both" || parts[0] == "" || parts[0] == "negative" || parts[0] == "positive") // adding both if role but no direction or direction without role
				parts[1] = "empty";
			else
				parts[1] = "both";
		}
		if(document.getElementById(parts[0]) && document.getElementById(parts[1]))
			if(!document.getElementById(parts[0]).checked || !document.getElementById(parts[1]).checked)
				losm.removeFeatures(feature);

		if(feature.attributes.type == "tmc:point")
		{
			// Check if this element is active
			if(document.getElementById('tmc:point'))
				if(!document.getElementById('tmc:point').checked)
					losm.removeFeatures(feature);

			feature.style.strokeWidth = 4.0;
			if(feature.attributes.role == "positive")
				feature.style.strokeColor = feature.style.fillColor = "#ff0000";
			else if(feature.attributes.role == "negative")
				feature.style.strokeColor = feature.style.fillColor = "#0000ff";
			else if(feature.attributes.role == "both" || feature.attributes.role == "")
				feature.style.strokeColor = feature.style.fillColor = "#ff00ff";
			else if(feature.attributes.role.match(/^positive:\w+$/))
				feature.style.strokeColor = feature.style.fillColor = "#800000";
			else if(feature.attributes.role.match(/^negative:\w+$/))
				feature.style.strokeColor = feature.style.fillColor = "#000080";
			else if(feature.attributes.role.match(/^both:\w+$/))
				feature.style.strokeColor = feature.style.fillColor = "#800080";
			else
				feature.style.strokeColor = feature.style.fillColor = "#000000";
		}
		else if(feature.attributes.type == "tmc:link")
		{
			// Check if this element is active
			if(document.getElementById('tmc:link'))
				if(!document.getElementById('tmc:link').checked)
					losm.removeFeatures(feature);

			feature.style.strokeWidth = 2.0;
			if(feature.attributes.role == "positive")
				feature.style.strokeColor = feature.style.fillColor = "#ff4040";
			else if(feature.attributes.role == "negative")
				feature.style.strokeColor = feature.style.fillColor = "#4040ff";
			else if(feature.attributes.role == "both" || feature.attributes.role == "")
				feature.style.strokeColor = feature.style.fillColor = "#ff80ff";
			else
				feature.style.strokeColor = feature.style.fillColor = "#000000";
		}
	};

	if(osmdata.features.length > 0)
	{
		losm = new OpenLayers.Layer.Vector("OSM Data", {
			style: {pointRadius: 7.5, fillOpacity: 0.5},
			onFeatureInsert: styleOSM
		});
		losm.events.register("featuresadded", losm, setExtent);
		losm.addFeatures(geojson.read(osmdata));
		map.addLayer(losm);
		layers.push(losm);
	}

	function styleTMC(feature) {
		if(feature.attributes.hasOwnProperty("angle"))
		{
			feature.style.rotation = feature.attributes.angle;
			feature.style.graphicName = "arrow";
			feature.style.pointRadius = 15;
		}
		else
		{
			feature.style.graphicName = "circle";
			feature.style.pointRadius = 7.5;
		}

		if(feature.attributes.hasOwnProperty("osm") && (feature.attributes.osm == 1))
			feature.style.fillOpacity = 0;
		else
			feature.style.fillOpacity = 1;
	};

	var ltmc = new OpenLayers.Layer.Vector("TMC Data", {
		style: {strokeColor: "#00cc00", strokeWidth: 2, fillColor: "#00ff00"},
		onFeatureInsert: styleTMC
	});
	ltmc.events.register("featuresadded", ltmc, setExtent);
	ltmc.addFeatures(geojson.read(tmcdata));
	map.addLayer(ltmc);
	layers.push(ltmc);

	function createPopup(feature) {
		var html = '<div>';
		if(!feature.attributes.hasOwnProperty("member"))
		{
			html += '<h3><a href="tmcview.php?cid=' + feature.attributes.cid + '&amp;tabcd=' + feature.attributes.tabcd + '&amp;lcd=' + feature.attributes.lcd + '">' + feature.attributes.cid + ':' + feature.attributes.tabcd + ':' + feature.attributes.lcd + '</a></h3>';
			html += '<p>' + feature.attributes.desc + '</p>';
		}
		else
		{
			html += '<h3><a href="http://www.openstreetmap.org/browse/' + feature.attributes.member + '/' + feature.attributes.id + '">' + feature.attributes.member + ':' + feature.attributes.id + '</a></h3>';
			html += '<ul>';
			html += '<li>Relation ID = <a href="http://www.openstreetmap.org/browse/relation/' + feature.attributes.relation + '">' + feature.attributes.relation + '</a></li>';
			html += '<li>Type = ' + feature.attributes.type + '</li>';
			html += '<li>Role = ' + feature.attributes.role + '</li>';
			if(feature.attributes.hasOwnProperty("lcd"))
				html += '<li>lcd = <a href="tmcview.php?cid=' + feature.attributes.table.replace(/:/, '&amp;tabcd=') + '&amp;lcd=' + feature.attributes.lcd + '">' + feature.attributes.lcd + '</a></li>';
			if(feature.attributes.hasOwnProperty("pos_lcd"))
				html += '<li>pos_lcd = <a href="tmcview.php?cid=' + feature.attributes.table.replace(/:/, '&amp;tabcd=') + '&amp;lcd=' + feature.attributes.pos_lcd + '">' + feature.attributes.pos_lcd + '</a></li>';
			if(feature.attributes.hasOwnProperty("neg_lcd"))
				html += '<li>neg_lcd = <a href="tmcview.php?cid=' + feature.attributes.table.replace(/:/, '&amp;tabcd=') + '&amp;lcd=' + feature.attributes.neg_lcd + '">' + feature.attributes.neg_lcd + '</a></li>';
			html += '</ul>';
		}
		html += '</div>';
		if(selcontrol.handlers.feature.evt.layerX && selcontrol.handlers.feature.evt.layerY)
			lonlat = map.getLonLatFromPixel(new OpenLayers.Pixel(selcontrol.handlers.feature.evt.layerX, selcontrol.handlers.feature.evt.layerY));
		else
			lonlat = feature.geometry.getBounds().getCenterLonLat();
		feature.popup = new OpenLayers.Popup.FramedCloud("gpx",
			lonlat,
			null,
			html,
			null,
			true,
			function() { selcontrol.unselectAll(); }
		);
		map.addPopup(feature.popup);
	}

	function destroyPopup(feature) {
		feature.popup.destroy();
		feature.popup = null;
	}

	var selcontrol = new OpenLayers.Control.SelectFeature(layers, {
		onSelect: createPopup,
		onUnselect: destroyPopup
	});
	map.addControl(selcontrol);
	selcontrol.activate();

	if(!map.getCenter())
		map.setCenter(null, null);
}

// Initialize available types, directions and roles in the osm data
function init_features()
{
	types = document.getElementById('types');
	roles = document.getElementById('roles');
	directions = document.getElementById('directions');
	if(!types || !roles || !directions)
		return;

	// find all elements
	var foundType = Array();
	var foundRole = Array();
	var foundDirection = Array();
	for(var i=0;i < osmdata.features.length;i++) {
		foundType[osmdata.features[i].properties.type] = true;
		var parts = osmdata.features[i].properties.role.split(":");
		if(parts.length<1 || parts[0] == "") {
			foundDirection["both"] = true;
			foundRole["empty"] = true;
		} else if(parts.length==2) {
			foundDirection[parts[0]] = true;
			foundRole[parts[1]] = true;
		} else { // Only first
			if(parts[0] == "both" || parts[0] == "" || parts[0] == "negative" || parts[0] == "positive") {
				foundDirection[parts[0]] = true;
				foundRole["empty"] = true;
			} else {
				foundDirection["both"] = true;
				foundRole[parts[0]] = true;
			}
		}
	}

	if(Object.keys(foundRole).length>0) roles.innerHTML += "Roles: ";
	for (var role in foundRole) {
		// Add Roles to List
		roles.innerHTML += create_input(role);
	}

	if(Object.keys(foundDirection).length>0) directions.innerHTML += "Directions: ";
	for (var direction in foundDirection) {
		// Add Directions to List
		directions.innerHTML += create_input(direction);
	}

	if(Object.keys(foundType).length>0) types.innerHTML += "Types: ";
	for (var type in foundType) {
		// Add Type to List
		types.innerHTML += create_input(type);
	}


}

function repaint_osm() {
	map.removeLayer(losm);
	losm.events.unregister("featuresadded", losm, setExtent); // deactivte zoom
	// Lösche alle Features
	losm.removeAllFeatures();
	// Füge Features wieder hinzu
	losm.addFeatures(geojson.read(osmdata));
	map.addLayer(losm);
}

function create_input(name) {
	return '<input type="checkbox" name="'+name+'" id="'+name+'" value="yes" checked="checked" onChange="repaint_osm()" /> ' + name;
}

function load_and_zoom()
{
	var bounds = map.getExtent().transform(new OpenLayers.Projection("EPSG:900913"), new OpenLayers.Projection("EPSG:4326"));
	var ba = bounds.toArray();
	document.getElementById('josm').src = "http://localhost:8111/load_and_zoom?left=" + ba[0] + "&bottom=" + ba[1] + "&right=" + ba[2] + "&top=" + ba[3];
}
