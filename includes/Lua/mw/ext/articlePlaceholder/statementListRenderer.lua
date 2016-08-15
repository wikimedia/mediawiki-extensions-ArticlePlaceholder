--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local statementListRenderer = {}

-- Get the datavalue for a given property.
--
-- @param string propertyId
-- @return string|nil datatype or nil if property couldn't be loaded
local getDatatype = function( propertyId )
  local property = mw.wikibase.getEntity( propertyId )
  return property and property['datatype']
end

-- Render a list of statements from an entity.
--
-- @param table entity
-- @return string wikitext
local render = function( self, entity )
  local result = ''
  local orderProperties = self._entityrenderer:getOrderProperties()
  local propertyIDs = orderProperties( entity )

  if propertyIDs ~= nil then
    local labelRenderer = self._entityrenderer:getLabelRenderer()
    local bestStatementRenderer = self._entityrenderer:getBestStatementRenderer()
    local imageProperty = self._entityrenderer.imageProperty

    for i=1, #propertyIDs do

      if propertyIDs[i] ~= imageProperty and getDatatype( propertyIDs[i] ) ~= "external-id" then
        result = result .. '<div class="articleplaceholder-statementgroup">'
        -- check if the label is 'coordinates' and upper case it
        -- this is necessary since headings will be rendered to id="*label*"
        -- and 'coordinates' has specific CSS values on most mayor Wikipedias
        local label = labelRenderer( propertyIDs[i] )
        if label == 'coordinates' then
          label = label:gsub("^%l", string.upper)
        end

        result = result .. '<h2>' .. label .. '</h2>'
        result = result .. bestStatementRenderer( entity, propertyIDs[i] )
        result = result .. '</div>'
      end
    end
  end

  return '<div class="articleplaceholder-statementgrouplist">' .. result .. '</div>'
end

-- Get a function which is bound to the given entityrenderer.
--
-- @param table entityrenderer
-- @return function
statementListRenderer.newRenderer = function( entityrenderer )
  local self = mw.clone( statementListRenderer )
  self._entityrenderer = entityrenderer
  self._render = render

  return function( entity )
    return self:_render( entity )
  end
end

return statementListRenderer
