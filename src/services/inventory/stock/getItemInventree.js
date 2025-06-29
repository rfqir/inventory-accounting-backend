import { getLocation } from './getLocation.js';
import { getPart } from './getPart.js';

async function getItem(locationName) {
    try {
        const location = await getLocation(locationName);
        try {
            const item = await getPart(location);
            return item;
        } catch (error) {
            console.error('error get stock');
            throw error
        }    
    } catch (error) {
        console.error('error getLocation: ', error);
        throw error
    }
}

export { getItem };
